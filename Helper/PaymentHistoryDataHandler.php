<?php
/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ordermanagement\Helper;

use JsonException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as OrderModel;
use ReflectionException;
use Resursbank\Core\Helper\Order;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\MissingValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Entry;
use Resursbank\Ecom\Lib\Model\PaymentHistory\EntryCollection;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Event;
use Resursbank\Ecom\Lib\Model\PaymentHistory\Result;
use Resursbank\Ecom\Lib\Model\PaymentHistory\User;
use Resursbank\Ecom\Lib\Model\Rco\Checkout;
use Resursbank\Ecom\Module\PaymentHistory\DataHandler\DataHandlerInterface;
use Resursbank\Ecom\Module\PaymentHistory\Translator;
use Resursbank\Ecom\Module\Rco\Repository;
use Resursbank\Ordermanagement\Api\Data\PaymentHistoryInterface;
use Resursbank\Ordermanagement\Model\PaymentHistory as PaymentHistoryModel;
use Resursbank\Ordermanagement\Model\PaymentHistoryFactory as PaymentHistoryModelFactory;
use Resursbank\Ordermanagement\Model\PaymentHistoryRepository;
use Throwable;

/**
 * Payment history data handler to utilise Magento database.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentHistoryDataHandler implements DataHandlerInterface
{
    /**
     * @param Order $orderHelper
     * @param PaymentHistoryRepository $repository
     * @param SearchCriteriaBuilder $searchBuilder
     * @param Log $log
     * @param PaymentHistoryModelFactory $factory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly Order $orderHelper,
        private readonly PaymentHistoryRepository $repository,
        private readonly SearchCriteriaBuilder $searchBuilder,
        private readonly Log $log,
        private readonly PaymentHistoryModelFactory $factory,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Write payment history entry to database.
     *
     * @param Entry $entry
     * @return void
     */
    public function write(Entry $entry): void
    {
        try {
            $order = $this->orderHelper->getOrderFromPaymentId(
                paymentId: $entry->paymentId
            );

            $status = $order->getStatus();
            $state = $order->getState();

            $model = $this->factory->create();
            $model->setPaymentId(
                identifier: (int) $order->getPayment()->getEntityId()
            );
            $model->setEvent(event: $entry->event->value);
            $model->setUser(user: $entry->user->value);
            $model->setResult(result: $entry->result->value);
            $model->setExtra(extra: $entry->extra);
            $model->setStatusFrom(status: $status);
            $model->setStateFrom(state: $state);
            $model->setCreatedAt(
                createdAt: date(format: 'Y-m-d H:i:s', timestamp: $entry->time)
            );

            $this->syncOrder(order: $order, entry: $entry);

            $model->setStatusTo(status: $order->getStatus());
            $model->setStateTo(state: $order->getState());

            if ($entry->user === User::ADMIN) {
                $model->setUserReference(
                    userReference: $this->getAdminHelper()->getUserName()
                );
            }

            $this->repository->save(entry: $model);
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }
    }

    /**
     * Resolve list of history entries from db.
     *
     * @param string $paymentId
     * @param null|Event $event
     * @return null|EntryCollection
     */
    public function getList(
        string $paymentId,
        ?Event $event = null
    ): ?EntryCollection {
        try {
            $order = $this->orderHelper->getOrderFromPaymentId(
                paymentId: $paymentId
            );

            if ($order === null) {
                throw new MissingValueException(
                    message: "Missing order for $paymentId"
                );
            }

            $search = $this->searchBuilder
                ->addFilter(
                    field: PaymentHistoryInterface::ENTITY_PAYMENT_ID,
                    value: $order->getPayment()->getEntityId()
                );

            if ($event !== null) {
                $search->addFilter(
                    field: PaymentHistoryInterface::ENTITY_EVENT,
                    value: $event->value
                );
            }

            /** @var PaymentHistoryInterface[] $items */
            $items = $this->repository
                ->getList(searchCriteria: $search->create())
                ->getItems();

            $data = [];

            foreach ($items as $item) {
                try {
                    $data[] = $this->convertEntry(
                        paymentHistory: $item,
                        paymentId: $paymentId,
                        order: $order
                    );
                } catch (Throwable $error) {
                    $this->log->exception(error: $error);
                }
            }

            if (!empty($data)) {
                return new EntryCollection(data: $data);
            }
        } catch (Throwable $error) {
            $this->log->exception(error: $error);
        }

        return null;
    }

    /**
     * Whether event has executed for payment id.
     *
     * @param string $paymentId
     * @param Event $event
     * @return bool
     */
    public function hasExecuted(
        string $paymentId,
        Event $event
    ): bool {
        $collection = $this->getList(paymentId: $paymentId, event: $event);

        if ($collection === null) {
            return false;
        }

        /** @var Entry $entry */
        foreach ($collection as $entry) {
            if ($entry->event === $event) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert a PaymentHistory legacy model to Entry model for Ecom widget.
     *
     * @param PaymentHistoryModel $paymentHistory
     * @param string $paymentId
     * @param OrderInterface $order
     * @return Entry
     * @throws AttributeCombinationException
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws FilesystemException
     * @throws TranslationException
     */
    private function convertEntry(
        PaymentHistoryModel $paymentHistory,
        string $paymentId,
        OrderInterface $order
    ): Entry {
        $statusFrom = $this->getStatusInfo(
            paymentHistory: $paymentHistory,
            label: 'status',
            property: 'status_from'
        );

        $statusFrom .= '<br />' . $this->getStatusInfo(
            paymentHistory: $paymentHistory,
            label: 'state',
            property: 'state_from'
        );

        $statusTo = $this->getStatusInfo(
            paymentHistory: $paymentHistory,
            label: 'status',
            property: 'status_to'
        );

        $statusTo .= '<br />' . $this->getStatusInfo(
            paymentHistory: $paymentHistory,
            label: 'state',
            property: 'state_to'
        );

        return new Entry(
            paymentId: $paymentId,
            event: $this->convertEvent(event: $paymentHistory->getEvent()),
            user: $this->convertUser(user: $paymentHistory->getUser()),
            time: strtotime(datetime: $paymentHistory->getCreatedAt()),
            result: $this->convertResult(result: $paymentHistory->getResult()),
            extra: $paymentHistory->getExtra(),
            previousOrderStatus: $statusFrom,
            currentOrderStatus: $statusTo,
            reference: (string) $order->getIncrementId(),
            userReference: $paymentHistory->getUserReference()
        );
    }

    /**
     * Resolve payment/checkout instance matching supplied payment id.
     *
     * @param Entry $entry
     * @return Checkout
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    private function getCheckout(
        Entry $entry
    ): Checkout {
        return Repository::get(id: $entry->paymentId);
    }

    /**
     * Sync order based on checkout status.
     *
     * @param OrderInterface $order
     * @param Entry $entry
     * @return void
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public function syncOrder(
        OrderInterface $order,
        Entry $entry
    ): void {
        $checkout = $this->getCheckout(entry: $entry);

        if ($checkout->isCaptured()) {
            $this->handleCapturedPayment(order: $order);
        } elseif ($checkout->isProcessing()) {
            $this->handleProcessingPayment(order: $order);
        } elseif ($checkout->isCancelled()) {
            $this->handleCancelledPayment(order: $order);
        } elseif ($checkout->isFailed()) {
            $this->handleFailedPayment(order: $order);
        } elseif ($checkout->isFrozen()) {
            $this->handleFrozenPayment(order: $order);
        }
    }

    /**
     * Handle payment frozen.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function handleFrozenPayment(OrderInterface $order): void
    {
        $order->setState(state: OrderModel::STATE_PAYMENT_REVIEW);
        $order->setStatus(status: OrderModel::STATE_PAYMENT_REVIEW);
        $this->orderRepository->save(entity: $order);
    }

    /**
     * Handle payment captured.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function handleCapturedPayment(OrderInterface $order): void
    {
        $order->setState(state: OrderModel::STATE_PROCESSING);
        $order->setStatus(status: 'processing');
        $this->orderRepository->save(entity: $order);
    }

    /**
     * Handle payment confirmed/processing.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function handleProcessingPayment(OrderInterface $order): void
    {
        $order->setState(state: OrderModel::STATE_PENDING_PAYMENT);
        $order->setStatus(status: 'pending');
        $this->orderRepository->save(entity: $order);
    }

    /**
     * Handle payment cancelled.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function handleCancelledPayment(OrderInterface $order): void
    {
        $order->setState(state: OrderModel::STATE_CANCELED);
        $order->setStatus(status: 'canceled');
        $this->orderRepository->save(entity: $order);
    }

    /**
     * Handle payment failed.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function handleFailedPayment(
        OrderInterface $order
    ): void {
        $order->setState(state: OrderModel::STATE_CANCELED);
        $order->setStatus(status: 'canceled');
        $this->orderRepository->save(entity: $order);
    }

    /**
     * Resolve admin helper instance.
     *
     * We cannot DI this because it in turn DI:s a session helper, which will
     * cause an error on our frontend when we initialize ECom because the
     * area code will not have been applied yet, and thus session based classes
     * cannot be initialized.
     *
     * We utilize this helper to extract admin username for our history entries.
     *
     * @return Admin
     */
    private function getAdminHelper(): Admin
    {
        return ObjectManager::getInstance()->create(type: Admin::class);
    }

    /**
     * Convert legacy event to ECom event.
     *
     * Some events are tracked through legacy modules and useful both for legacy
     * flows and modern flows.
     *
     * @param null|string $event
     * @return Event
     */
    private function convertEvent(
        ?string $event
    ): Event {
        $map = [
            PaymentHistoryInterface::EVENT_REACHED_ORDER_SUCCESS => Event::REACHED_ORDER_SUCCESS_PAGE->value,
            PaymentHistoryInterface::EVENT_REACHED_ORDER_FAILURE=>  Event::REACHED_ORDER_FAILURE_PAGE->value,
            PaymentHistoryInterface::EVENT_ORDER_CANCELED =>  Event::ORDER_CANCELED->value,
            PaymentHistoryInterface::EVENT_ORDER_CANCELED_CRON => Event::ORDER_CANCELED_CRON->value,
            PaymentHistoryInterface::EVENT_INVOICE_CREATED =>  Event::INVOICE_CREATED->value,
            PaymentHistoryInterface::EVENT_GATEWAY_REDIRECTED_TO =>  Event::REDIRECTED_TO_GATEWAY->value
        ];

        return Event::from(value: $map[$event] ?? $event);
    }

    /**
     * Convert legacy user to ECom user.
     *
     * This is useful for legacy events we still track for modern flows.
     *
     * @param null|string $user
     * @return User
     */
    private function convertUser(
        ?string $user
    ): User {
        $map = [
            PaymentHistoryInterface::USER_CLIENT => User::ADMIN->value,
            PaymentHistoryInterface::USER_CUSTOMER=> User::CUSTOMER->value,
            PaymentHistoryInterface::USER_RESURS_BANK =>  User::RESURSBANK->value
        ];

        return User::from(value: $map[$user] ?? $user);
    }

    /**
     * Resolve event result..
     *
     * @param string|null $result
     * @return Result
     */
    private function convertResult(
        ?string $result
    ): Result {
        return (string) $result !== '' ?
            Result::from(value: $result) : Result::INFO;
    }

    /**
     * Get data for status column.
     *
     * @param PaymentHistoryModel $paymentHistory
     * @param string $label
     * @param string $property
     * @return string
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private function getStatusInfo(
        PaymentHistoryModel $paymentHistory,
        string $label,
        string $property
    ): string {
        $data = trim(string: (string) $paymentHistory->getData(key: $property));

        return Translator::translate(phraseId: $label) . ": $data";
    }
}

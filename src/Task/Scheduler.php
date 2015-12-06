<?php

namespace Task;

use Psr\Log\LoggerInterface;
use Task\FrequentTask\FrequentTaskInterface;
use Task\Handler\RegistryInterface;
use Task\Storage\StorageInterface;

class Scheduler implements SchedulerInterface
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var RegistryInterface
     */
    private $registry;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(StorageInterface $storage, RegistryInterface $registry, LoggerInterface $logger = null)
    {
        $this->storage = $storage;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function createTask($taskName, $workload)
    {
        return TaskBuilder::create($this, $taskName, $workload);
    }

    /**
     * {@inheritdoc}
     */
    public function schedule(TaskInterface $task)
    {
        if (!$this->registry->has($task->getTaskName())) {
            throw new \Exception($this->getNoHandlerFoundMessage($task));
        }

        $this->storage->store($task);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        /** @var TaskInterface $task */
        foreach ($this->storage->findScheduled() as $task) {
            if (!$this->registry->has($task->getTaskName())) {
                $this->warning($this->getNoHandlerFoundMessage($task));
                continue;
            }

            $result = $this->registry->run($task->getTaskName(), $task->getWorkload());

            $task->setResult($result);
            $task->setCompleted();

            // TODO move to event-dispatcher
            if ($task instanceof FrequentTaskInterface) {
                $task->scheduleNext($this);
            }
        }
    }

    /**
     * Returns message for "no handler found".
     *
     * @param TaskInterface $task
     *
     * @return string
     */
    private function getNoHandlerFoundMessage(TaskInterface $task)
    {
        return sprintf('No handler found handler for "%s" task.', $task->getTaskName());
    }

    /**
     * Write a warning into log.
     *
     * @param string $message
     */
    private function warning($message)
    {
        if (null !== $this->logger) {
            return;
        }

        $this->logger->warning($message);
    }
}
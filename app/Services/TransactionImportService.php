<?php

namespace App\Services;

use App\DataObjects\TransactionData;
use App\Entity\Transaction;
use App\Entity\User;
use Clockwork\Clockwork;
use Doctrine\ORM\EntityManager;
use Psr\Log\LogLevel;

class TransactionImportService
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly TransactionService $transactionService,
        private readonly EntityManager $entityManager,
        private readonly Clockwork $clockwork,
    )
    {
    }

    public function importFromFile(string $file, User $user): void
    {
        $resource = fopen($file, 'r');
        $categories = $this->categoryService->getAllKeyedByName();

        fgetcsv($resource);

        $this->clockwork->log(LogLevel::DEBUG, 'Memory Usage Before: ' . memory_get_usage());
        $this->clockwork->log(LogLevel::DEBUG, 'Unit Of Work Before: ' . $this->entityManager->getUnitOfWork()->size());

        $count = 1;
        $batchSize = 250;
        while (($row = fgetcsv($resource)) !== false) {
            [$date, $description, $category, $amount] = $row;

            $date     = new \DateTime($date);
            $category = $categories[strtolower($category)] ?? null;
            $amount   = str_replace(['$', ','], '', $amount);

            $transactionData = new TransactionData($description, (float) $amount, $date, $category);

            $this->transactionService->create($transactionData, $user);

            if ($count % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(Transaction::class);
                $count = 1;
            } else{
                $count++;
            }
        }

        if ($count > 1) {
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $this->clockwork->log(LogLevel::DEBUG, 'Memory Usage After: ' . memory_get_usage());
        $this->clockwork->log(LogLevel::DEBUG, 'Unit Of Work After: ' . $this->entityManager->getUnitOfWork()->size());
    }
}
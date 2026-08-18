<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds current period demonstration data for the first user', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->seed(DemoDataSeeder::class);

    $this->assertModelExists($firstUser);
    $this->assertModelExists($secondUser);

    expect(Category::query()->whereBelongsTo($firstUser)->count())->toBe(10)
        ->and(Tag::query()->whereBelongsTo($firstUser)->count())->toBe(6)
        ->and(Card::query()->whereBelongsTo($firstUser)->count())->toBe(3)
        ->and(Transaction::query()->whereBelongsTo($firstUser)->count())->toBe(13)
        ->and(InstallmentPlan::query()->whereBelongsTo($firstUser)->count())->toBe(2)
        ->and(Category::query()->whereBelongsTo($secondUser)->count())->toBe(0);

    $currentPeriod = now()->format('Y-m');
    $currentPeriodIncomes = Transaction::query()
        ->whereBelongsTo($firstUser)
        ->where('type', 'income')
        ->where('purchase_date', 'like', "{$currentPeriod}%")
        ->count();
    $currentPeriodInstallments = InstallmentPlan::query()
        ->whereBelongsTo($firstUser)
        ->whereHas('installments', fn ($query) => $query->where('due_date', 'like', "{$currentPeriod}%"))
        ->count();

    expect($currentPeriodIncomes)->toBe(4)
        ->and($currentPeriodInstallments)->toBe(2);

    $this->seed(DemoDataSeeder::class);

    expect(Transaction::query()->whereBelongsTo($firstUser)->count())->toBe(13)
        ->and(InstallmentPlan::query()->whereBelongsTo($firstUser)->count())->toBe(2);
});

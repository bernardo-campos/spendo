<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InstallmentPlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    private const string DemoNotes = 'Datos ficticios para pruebas manuales.';

    /**
     * Seed demonstration financial data for the first user in the database.
     */
    public function run(InstallmentPlanService $installmentPlanService): void
    {
        $user = User::query()->oldest('id')->first();

        if ($user === null) {
            return;
        }

        $periodStart = now()->startOfMonth();
        $categories = $this->seedCategories($user);
        $tags = $this->seedTags($user);
        $cards = $this->seedCards($user);

        $user->transactions()->where('notes', self::DemoNotes)->delete();

        $this->createTransaction($user, $categories['salary'], $this->tagIds($tags, ['work']), [
            'type' => 'income',
            'description' => 'Salario mensual',
            'amount' => '1800000.00',
            'purchase_date' => $periodStart->copy()->addDay()->toDateString(),
            'payment_date' => null,
            'payment_method' => null,
        ]);
        $this->createTransaction($user, $categories['freelance'], $this->tagIds($tags, ['work']), [
            'type' => 'income',
            'description' => 'Proyecto freelance',
            'amount' => '420000.00',
            'purchase_date' => $periodStart->copy()->addDays(5)->toDateString(),
            'payment_date' => null,
            'payment_method' => null,
        ]);
        $this->createTransaction($user, $categories['returns'], [], [
            'type' => 'income',
            'description' => 'Rendimientos de inversión',
            'amount' => '25000.00',
            'purchase_date' => $periodStart->copy()->addDays(10)->toDateString(),
            'payment_date' => null,
            'payment_method' => null,
        ]);
        $this->createTransaction($user, $categories['sales'], $this->tagIds($tags, ['home']), [
            'type' => 'income',
            'description' => 'Venta de artículos usados',
            'amount' => '68000.00',
            'purchase_date' => $periodStart->copy()->addDays(15)->toDateString(),
            'payment_date' => null,
            'payment_method' => null,
        ]);

        $this->createTransaction($user, $categories['food'], $this->tagIds($tags, ['essential', 'home']), [
            'type' => 'expense',
            'description' => 'Supermercado',
            'amount' => '120000.00',
            'purchase_date' => $periodStart->copy()->addDays(2)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(2)->toDateString(),
            'payment_method' => 'cash',
        ]);
        $this->createTransaction($user, $categories['transport'], $this->tagIds($tags, ['essential']), [
            'type' => 'expense',
            'description' => 'Transporte',
            'amount' => '28000.00',
            'purchase_date' => $periodStart->copy()->addDays(3)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(3)->toDateString(),
            'payment_method' => 'cash',
        ]);
        $this->createTransaction($user, $categories['home'], $this->tagIds($tags, ['home', 'recurring']), [
            'type' => 'expense',
            'description' => 'Internet y telefonía',
            'amount' => '55000.00',
            'purchase_date' => $periodStart->copy()->addDays(5)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(5)->toDateString(),
            'payment_method' => 'cash',
        ]);
        $this->createTransaction($user, $categories['health'], $this->tagIds($tags, ['health', 'essential']), [
            'type' => 'expense',
            'description' => 'Farmacia',
            'amount' => '23500.00',
            'purchase_date' => $periodStart->copy()->addDays(11)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(11)->toDateString(),
            'payment_method' => 'cash',
        ]);
        $this->createTransaction($user, $categories['entertainment'], $this->tagIds($tags, ['leisure']), [
            'type' => 'expense',
            'description' => 'Cena con amigos',
            'amount' => '48000.00',
            'purchase_date' => $periodStart->copy()->addDays(14)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(14)->toDateString(),
            'payment_method' => 'cash',
        ]);
        $this->createTransaction($user, $categories['subscriptions'], $this->tagIds($tags, ['recurring', 'leisure']), [
            'type' => 'expense',
            'description' => 'Suscripción de streaming',
            'amount' => '15000.00',
            'purchase_date' => $periodStart->copy()->addDays(16)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(16)->toDateString(),
            'payment_method' => 'cash',
        ]);
        $this->createTransaction($user, $categories['entertainment'], $this->tagIds($tags, ['leisure']), [
            'type' => 'expense',
            'description' => 'Zapatillas deportivas',
            'amount' => '95000.00',
            'purchase_date' => $periodStart->copy()->addDays(9)->toDateString(),
            'payment_date' => $periodStart->copy()->addDays(9)->toDateString(),
            'payment_method' => 'credit',
            'card_id' => $cards['visa']->id,
        ]);

        $this->createInstallmentTransaction(
            user: $user,
            category: $categories['home'],
            card: $cards['visa'],
            tagIds: $this->tagIds($tags, ['home', 'work']),
            description: 'Notebook para trabajo',
            totalAmount: '900000.00',
            purchaseDate: $periodStart->copy()->subMonth()->addDays(18),
            firstDueDate: $periodStart->copy()->addDays(12),
            installmentsCount: 3,
            installmentPlanService: $installmentPlanService,
        );
        $this->createInstallmentTransaction(
            user: $user,
            category: $categories['entertainment'],
            card: $cards['mastercard'],
            tagIds: $this->tagIds($tags, ['leisure']),
            description: 'Teléfono móvil',
            totalAmount: '480000.00',
            purchaseDate: $periodStart->copy()->subMonth()->addDays(7),
            firstDueDate: $periodStart->copy()->addDays(20),
            installmentsCount: 6,
            installmentPlanService: $installmentPlanService,
        );
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(User $user): array
    {
        $categories = [
            'salary' => ['name' => 'Salario', 'scope' => 'income'],
            'freelance' => ['name' => 'Trabajo independiente', 'scope' => 'income'],
            'returns' => ['name' => 'Rendimientos', 'scope' => 'income'],
            'sales' => ['name' => 'Ventas', 'scope' => 'income'],
            'food' => ['name' => 'Alimentación', 'scope' => 'expense'],
            'transport' => ['name' => 'Transporte', 'scope' => 'expense'],
            'home' => ['name' => 'Hogar', 'scope' => 'expense'],
            'health' => ['name' => 'Salud', 'scope' => 'expense'],
            'entertainment' => ['name' => 'Entretenimiento', 'scope' => 'expense'],
            'subscriptions' => ['name' => 'Suscripciones', 'scope' => 'expense'],
        ];

        foreach ($categories as $slug => $category) {
            $categories[$slug] = $user->categories()->firstOrCreate(
                ['slug' => $slug],
                [...$category, 'is_active' => true],
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Tag>
     */
    private function seedTags(User $user): array
    {
        $tags = [
            'essential' => 'Esencial',
            'recurring' => 'Recurrente',
            'work' => 'Trabajo',
            'leisure' => 'Ocio',
            'home' => 'Hogar',
            'health' => 'Salud',
        ];

        foreach ($tags as $slug => $name) {
            $tags[$slug] = $user->tags()->firstOrCreate(['slug' => $slug], ['name' => $name]);
        }

        return $tags;
    }

    /**
     * @return array<string, Card>
     */
    private function seedCards(User $user): array
    {
        $cards = [
            'visa' => ['name' => 'Visa Platinum', 'last_four_digits' => '4821', 'closing_day' => 25, 'due_day' => 10],
            'mastercard' => ['name' => 'Mastercard Gold', 'last_four_digits' => '7914', 'closing_day' => 18, 'due_day' => 5],
            'amex' => ['name' => 'American Express', 'last_four_digits' => '3008', 'closing_day' => 12, 'due_day' => 28],
        ];

        foreach ($cards as $key => $card) {
            $cards[$key] = $user->cards()->firstOrCreate(
                ['name' => $card['name']],
                [...$card, 'is_active' => true],
            );
        }

        return $cards;
    }

    /**
     * @param  array<string, Tag>  $tags
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function tagIds(array $tags, array $slugs): array
    {
        return array_map(fn (string $slug): int => $tags[$slug]->id, $slugs);
    }

    /**
     * @param  list<int>  $tagIds
     * @param  array{type:string, description:string, amount:string, purchase_date:string, payment_date:?string, payment_method:?string, card_id?:int}  $attributes
     */
    private function createTransaction(User $user, Category $category, array $tagIds, array $attributes): Transaction
    {
        $transaction = $user->transactions()->create([
            ...$attributes,
            'category_id' => $category->id,
            'notes' => self::DemoNotes,
        ]);

        $transaction->tags()->sync($tagIds);

        return $transaction;
    }

    /**
     * @param  list<int>  $tagIds
     */
    private function createInstallmentTransaction(
        User $user,
        Category $category,
        Card $card,
        array $tagIds,
        string $description,
        string $totalAmount,
        Carbon $purchaseDate,
        Carbon $firstDueDate,
        int $installmentsCount,
        InstallmentPlanService $installmentPlanService,
    ): void {
        $transaction = $this->createTransaction($user, $category, $tagIds, [
            'type' => 'expense',
            'description' => $description,
            'amount' => $totalAmount,
            'purchase_date' => $purchaseDate->toDateString(),
            'payment_date' => $firstDueDate->toDateString(),
            'payment_method' => 'credit',
            'card_id' => $card->id,
        ]);

        $plan = $transaction->installmentPlan()->create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'installments_count' => $installmentsCount,
            'total_amount' => $totalAmount,
            'first_due_date' => $firstDueDate->toDateString(),
            'status' => 'pending',
        ]);

        $plan->installments()->createMany(
            $installmentPlanService->buildInstallments(
                $totalAmount,
                $installmentsCount,
                $firstDueDate->toDateString(),
            ),
        );
    }
}

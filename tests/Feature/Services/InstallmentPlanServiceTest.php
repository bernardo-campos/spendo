<?php

use App\Services\InstallmentPlanService;

test('it builds installments with monthly due dates and amount split', function () {
    $service = new InstallmentPlanService;

    $installments = $service->buildInstallments('1200.00', 3, '2026-04-10');

    expect($installments)->toHaveCount(3)
        ->and($installments[0]['amount'])->toBe('400.00')
        ->and($installments[1]['amount'])->toBe('400.00')
        ->and($installments[2]['amount'])->toBe('400.00')
        ->and($installments[0]['due_date'])->toBe('2026-04-10')
        ->and($installments[1]['due_date'])->toBe('2026-05-10')
        ->and($installments[2]['due_date'])->toBe('2026-06-10');
});

test('it assigns remainder to last installment when split is not exact', function () {
    $service = new InstallmentPlanService;

    $installments = $service->buildInstallments('100.00', 3, '2026-04-01');

    expect($installments[0]['amount'])->toBe('33.33')
        ->and($installments[1]['amount'])->toBe('33.33')
        ->and($installments[2]['amount'])->toBe('33.34');
});

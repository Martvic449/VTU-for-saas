<?php

use App\Services\Transactions\TransactionStateMachine;

it('does not treat a submitted or pending transaction as safe to blindly retry', function () {
    $machine = new TransactionStateMachine();
    expect($machine->canRetryProviderRequest('created'))->toBeTrue();
    expect($machine->canRetryProviderRequest('reserved'))->toBeTrue();
    expect($machine->canRetryProviderRequest('submitted'))->toBeFalse();
    expect($machine->canRetryProviderRequest('pending'))->toBeFalse();
});

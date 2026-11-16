<?php

use App\Services\TotpService;

it('verifies a standards-based TOTP test vector', function () {
    expect(app(TotpService::class)->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59, 0))->toBeTrue();
    expect(app(TotpService::class)->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000', 59, 0))->toBeFalse();
});

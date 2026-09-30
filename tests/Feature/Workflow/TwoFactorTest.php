<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Two-factor authentication with an authenticator app: once a user turns
 * it on from their profile, a password alone no longer signs them in.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->owner->forceFill(['password' => 'password'])->save();
});

it('signs in with a password alone until an authenticator app is set up', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => $this->owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();
    expect(auth()->id())->toBe($this->owner->id);
});

it('challenges for the app code once set up, accepts the current code, and a recovery code once', function () {
    $provider = collect(Filament::getMultiFactorAuthenticationProviders())->first(fn ($p) => $p instanceof AppAuthentication);
    expect($provider)->toBeInstanceOf(AppAuthentication::class);

    $secret = $provider->generateSecret();
    $this->owner->saveAppAuthenticationSecret($secret);
    $this->owner->saveAppAuthenticationRecoveryCodes([Hash::make('alpha-recovery-code'), Hash::make('bravo-recovery-code')]);   // stored hashed, like passwords

    // The password alone now stops at the challenge.
    $login = Livewire::test(Login::class)
        ->fillForm(['email' => $this->owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();
    expect(auth()->check())->toBeFalse()
        ->and($login->get('userUndertakingMultiFactorAuthentication'))->not->toBeNull();

    // A wrong code is refused; the right one signs in.
    $codeField = "data.multiFactor.{$provider->getId()}.code";
    $login->set($codeField, '000000')->call('authenticate')->assertHasErrors([$codeField]);
    expect(auth()->check())->toBeFalse();
    $login->set($codeField, $provider->getCurrentCode($this->owner))->call('authenticate')->assertHasNoErrors();
    expect(auth()->id())->toBe($this->owner->id);

    // A recovery code works instead, and is spent.
    auth()->logout();
    Livewire::test(Login::class)
        ->fillForm(['email' => $this->owner->email, 'password' => 'password'])
        ->call('authenticate')
        ->set("data.multiFactor.{$provider->getId()}.useRecoveryCode", true)
        ->set("data.multiFactor.{$provider->getId()}.recoveryCode", 'alpha-recovery-code')
        ->call('authenticate')
        ->assertHasNoErrors();
    $remaining = User::query()->find($this->owner->id)->getAppAuthenticationRecoveryCodes();
    expect(auth()->id())->toBe($this->owner->id)
        ->and($remaining)->toHaveCount(1)
        ->and(Hash::check('bravo-recovery-code', $remaining[0]))->toBeTrue();
});

it('offers the authenticator set-up on the profile page', function () {
    $this->actingAs($this->owner->fresh());
    Filament::setTenant($this->company);

    Livewire::test(EditProfile::class)->assertOk()->assertSee('Authenticator app');
});

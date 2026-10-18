<?php

use App\Models\User;
use Illuminate\Support\Facades\Vite;

it('renders the landing page for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('LoyUp')
        ->assertSee('id="rezultate"', false)
        ->assertSee('id="cum-functioneaza"', false)
        ->assertSee('id="pentru-cine"', false)
        ->assertSee('id="anti-frauda"', false)
        ->assertSee('id="roluri"', false)
        ->assertSee('Începe gratuit')
        ->assertSee(route('login'))
        ->assertSee(route('register'))
        ->assertSee('href="'.route('customer.login').'"', false);
});

it('has a single h1 with the headline', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('Clienții revin.');
});

it('shows the program examples from the business templates', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Cafenele', 'Restaurante', 'Spălătorii auto', 'Frizerii']);
});

it('no longer uses the old palette images or the script font', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('images/landing/', false)
        ->assertDontSee('kaushan', false);
});

it('shows the Figma logo lockup: the LoyUp symbol and the name', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('viewBox="0 0 444 397"', false)
        ->assertSee('>LoyUp</span>', false);
});

it('links logged-in users to the dashboard instead of the sign-up', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('dashboard'))
        ->assertDontSee('Începe gratuit')
        ->assertDontSee(route('register'));
});

it('uses the local Figma photos, never temporary Figma asset URLs', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    foreach (['hero-barista', 'scan-counter', 'cafenele', 'restaurante', 'spalatorii', 'frizerii', 'secure-scan', 'roles', 'cta-bg'] as $image) {
        expect($html)->toContain("images/marketing/{$image}.webp");
        expect(public_path("images/marketing/{$image}.webp"))->toBeFile();
    }

    expect($html)->not->toContain('figma.com/api/mcp/asset');
});

it('keeps the call to action illustration decorative and lazy', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Primul program e gata')
        ->assertSee('src="'.asset('images/marketing/cta-bg.webp').'" alt="" aria-hidden="true"', false);
});

it('loads the landing animations only on the marketing pages', function () {
    // Vite în mod „hot”: testul nu depinde de un `npm run build` proaspăt.
    $hot = tempnam(sys_get_temp_dir(), 'vite-hot');
    file_put_contents($hot, 'http://vite.test');
    Vite::useHotFile($hot);

    $this->get(route('home'))->assertOk()->assertSee('resources/js/landing.js', false)->assertSee('data-anim="steps"', false);
    $this->get(route('login'))->assertOk()->assertDontSee('resources/js/landing.js', false);

    unlink($hot);
});

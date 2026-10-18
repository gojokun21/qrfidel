<?php

function loyaltyCard(array $props = []): string
{
    $props = array_merge([
        'business' => 'Cafenea Demo',
        'name' => '5 cafele = 1 gratis',
        'required' => 5,
        'reward' => 'Cafea gratis',
        'color' => '#7c4a2d',
        'filled' => 3,
    ], $props);

    return (string) test()->blade(
        '<x-loyalty.card :business="$business" :name="$name" :required="$required" :reward="$reward" :color="$color" :filled="$filled" />',
        $props,
    );
}

it('shows the progress only once, through the stamp slots', function () {
    $html = loyaltyCard();

    expect($html)
        ->toContain('Card de fidelitate: 3 din 5 ștampile')
        ->toContain('Cafea gratis')
        ->not->toContain('Mai ai nevoie de')
        ->not->toContain('Recompensă<')
        ->not->toContain('>Ștampile<')
        ->not->toContain('/<span>5</span>');

    expect(substr_count($html, 'aspect-square'))->toBe(5);
});

it('keeps a complete card free of extra status text', function () {
    expect(loyaltyCard(['filled' => 5]))
        ->toContain('Card de fidelitate: 5 din 5 ștampile')
        ->not->toContain('Completat');
});

it('paints the card in the program color with readable ink', function () {
    expect(loyaltyCard(['color' => '#7c4a2d']))->toContain('background-color: #7c4a2d; color: #ffffff');
});

it('drops the decorative glow, the dashed slots and the duplicate progress bar', function () {
    expect(loyaltyCard())
        ->not->toContain('blur-2xl')
        ->not->toContain('border-dashed')
        ->not->toContain('bg-inverse-line');
});

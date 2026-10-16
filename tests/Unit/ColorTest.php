<?php

use App\Support\Color;

it('picks dark text on light backgrounds and white text on dark ones', function (string $background, string $text) {
    expect(Color::readableOn($background))->toBe($text);
})->with([
    'gold' => ['#CC9933', Color::DARK],
    'white' => ['#ffffff', Color::DARK],
    'yellow' => ['#facc15', Color::DARK],
    'espresso' => ['#1c1410', Color::LIGHT],
    'blue' => ['#1d4ed8', Color::LIGHT],
    'brick' => ['#7c2d12', Color::LIGHT],
    // Aproape de prag: contrastul se măsoară cu textul închis real, nu cu negru pur.
    'mid grey' => ['#777777', Color::LIGHT],
]);

it('recognises #RRGGBB colors only', function (?string $value, bool $valid) {
    expect(Color::isHex($value))->toBe($valid);
})->with([
    ['#7c2d12', true],
    ['#7C2D12', true],
    ['#abc', false],
    ['7c2d12', false],
    ['red', false],
    [null, false],
]);

it('falls back to dark text for an invalid color', function () {
    expect(Color::readableOn('red'))->toBe(Color::DARK);
});

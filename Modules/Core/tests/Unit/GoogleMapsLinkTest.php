<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Core\Support\GoogleMapsLink;

it('reads coordinates from the common Google Maps URL shapes', function (string $url, float $lat, float $lng): void {
    expect(GoogleMapsLink::coordinatesFrom($url))->toBe(['lat' => $lat, 'lng' => $lng]);
})->with([
    'place pin beats viewport' => ['https://www.google.com/maps/place/Arugam+Bay/@6.84,81.83,15z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d6.8406123!4d81.8368456', 6.8406123, 81.8368456],
    'viewport only' => ['https://www.google.com/maps/@6.9271,79.8612,14z', 6.9271, 79.8612],
    'query parameter' => ['https://maps.google.com/?q=7.2906,80.6337', 7.2906, 80.6337],
    'api=1 search' => ['https://www.google.com/maps/search/?api=1&query=6.0535%2C80.2210', 6.0535, 80.221],
    'coordinates as place' => ['https://www.google.com/maps/place/6.0329,80.2168', 6.0329, 80.2168],
    'directions destination' => ['https://www.google.com/maps/dir/?api=1&destination=-8.65,115.13', -8.65, 115.13],
    'bare text' => [' 6.8406, 81.8368 ', 6.8406, 81.8368],
]);

it('finds nothing in a link without coordinates', function (): void {
    expect(GoogleMapsLink::coordinatesFrom('https://maps.app.goo.gl/AbCdEf123'))->toBeNull()
        ->and(GoogleMapsLink::coordinatesFrom('https://www.google.com/maps/place/Arugam+Bay'))->toBeNull()
        ->and(GoogleMapsLink::coordinatesFrom('https://www.google.com/maps/@0,0,3z'))->toBeNull();
});

it('only accepts Google Maps links', function (): void {
    expect(GoogleMapsLink::isGoogleMapsUrl('https://maps.app.goo.gl/AbCdEf123'))->toBeTrue()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://goo.gl/maps/AbCdEf123'))->toBeTrue()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://www.google.com/maps/place/X'))->toBeTrue()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://www.google.lk/maps/@6.9,79.8,14z'))->toBeTrue()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://maps.google.co.uk/?q=1,2'))->toBeTrue()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://www.google.com/search?q=maps'))->toBeFalse()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://goo.gl/AbCdEf'))->toBeFalse()
        ->and(GoogleMapsLink::isGoogleMapsUrl('https://google.com.evil.example/maps'))->toBeFalse()
        ->and(GoogleMapsLink::isGoogleMapsUrl('javascript:alert(1)//google.com/maps'))->toBeFalse();
});

it('follows a short link through its redirects to the coordinates', function (): void {
    Http::fake([
        'https://maps.app.goo.gl/AbCdEf123' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Cabana/@6.84,81.83,17z/data=!3d6.8401!4d81.8302']),
    ]);

    expect(GoogleMapsLink::resolveCoordinates('https://maps.app.goo.gl/AbCdEf123'))
        ->toBe(['lat' => 6.8401, 'lng' => 81.8302]);
});

it('never follows a short link away from Google', function (): void {
    Http::fake([
        'https://maps.app.goo.gl/Evil' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
        '*' => Http::response('should not be fetched', 200),
    ]);

    expect(GoogleMapsLink::resolveCoordinates('https://maps.app.goo.gl/Evil'))->toBeNull();
    Http::assertSentCount(1);
});

it('does not touch the network for a full link or a non-Google link', function (): void {
    Http::fake();

    GoogleMapsLink::resolveCoordinates('https://www.google.com/maps/@6.9271,79.8612,14z');
    GoogleMapsLink::resolveCoordinates('https://example.com/maps');

    Http::assertNothingSent();
});

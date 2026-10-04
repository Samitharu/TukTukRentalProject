# Stays (Cabanas, Rooms) and Unit Locations

The business also rents out cabanas and rooms in several places, and sells them as packages ("Surf & Stay": a cabana plus surf lessons). This document explains how they fit into a system built for tuk tuk rentals.

## One engine, two kinds of unit

A cabana or room is booked the same way as a tuk tuk: one unit, a run of calendar days, and never double-booked. So stays reuse the existing Fleet → Availability → Booking path rather than adding a parallel one. The `vehicles` table and the `vehicle_id` columns keep their names. In code and in the admin, a row of `vehicles` is now a **unit**.

- `vehicle_categories.kind` is `vehicle` (tuk tuks, rented by the day) or `stay` (cabanas/rooms, booked by the night). A unit gets its kind from its category (`Vehicle::isStay()`). The kind of a category can't be changed once it has units.
- `packages.kind` uses the same two values. A package books exactly one kind. Its unrestricted "any unit" fallback (`Package::eligibleVehicleIds()`) is limited to that kind, so a tuk tuk package never auto-assigns a cabana. Admin validation stops a package from being restricted to units or categories of the other kind.
- Stays have no plate, transmission or fuel type (those columns are now nullable). `seats` means "maximum guests".
- Surf lessons, boards and transfers are **add-ons** on a stay package; mark one *included* to bundle it into the price.

## Nights: how a stay is stored

**For a stay, `end_at` (and the flow's `end_date`) is the last night, not the check-out day.**

A 3-night stay with check-in on 10 Jan and check-out on 13 Jan is stored as `start_at = 10 Jan`, `end_at = 12 Jan`. It reserves the slots for the 10th, 11th and 12th. That is exactly the day-inclusive shape a tuk tuk rental already uses, so pricing (`days = nights`), availability, `vehicle_reservation_slots` and every `BookingService` method work unchanged. The next guest can check in on the 13th.

The conversion only happens at the edges:

| Where | Direction |
|---|---|
| `StepDatesRequest::flowData()` (public wizard) | check-out → last night |
| `BookingController::store()` (admin manual booking) | check-out → last night |
| `ChangeDatesRequest::range()` (admin date change) | check-out → last night |
| `Booking::checkOutDate()` | last night → check-out, for display |

Any view showing a booking's end date should use `checkOutDate()`, not `end_at`. Use `lengthInDays()` for the number of days or nights.

## Booking wizard differences for stays

The wizard's kind (`BookingFlowState::kind()`) is set when the customer arrives from a stay page (`?vehicle=`), a stay package (`?package=`) or `?kind=stay`. Step 1 also has a Tuk tuks / Stays switch. Switching kind clears the earlier choices, because a stored end date means something different for each kind. For a stay:

- dates are check-in / check-out, with at least one night;
- there is no pickup method or location (stored as `office` with no location);
- the details step has no driving-licence or IDP questions;
- only stay packages are offered, and texts say "nights", "per night" and "pay on arrival".

## Google Maps locations

Each unit has `address`, `google_maps_url`, `lat` and `lng`. A location is required for stays and optional for tuk tuks.

- **Admin input.** Staff paste the "Share → Copy link" URL from Google Maps. `Modules\Core\Support\GoogleMapsLink` reads the coordinates from full URLs (`!3d…!4d…` place pin, `?q=`, `@lat,lng`). For `maps.app.goo.gl` short links it follows the redirect server-side on save, checking every hop is a Google host so a pasted link can't be used for SSRF. The form also has a Leaflet map for dropping or dragging the pin. `public/assets/js/maps.js` parses full links in the browser with the same patterns.
- **Only Google Maps links are accepted** (`Modules\Core\Rules\GoogleMapsUrl`). The link is rendered on the public site, so an arbitrary URL would be a phishing vector.
- **Public display.** An OpenStreetMap preview (Leaflet), plus "Open in Google Maps" and "Get directions" links (`https://www.google.com/maps/dir/?api=1&destination=…`). None of this needs a Google API key or billing.
- Leaflet 1.9.4 is vendored under `public/assets/vendor/leaflet/`, like Alpine, so the CSP needed no change. Map tiles come from `tile.openstreetmap.org` (allowed by `img-src https:`). That is fine for this traffic level under the OSM tile usage policy; switch to a commercial tile provider if traffic grows a lot.

## Public URLs

- `/{locale}/tuk-tuks` — tuk tuks only.
- `/{locale}/stays` and `/{locale}/stays/{slug}` — cabanas and rooms. A unit opened under the wrong prefix gets a 301 redirect to its canonical URL.
- A stay package's page lists "Where you'll stay", with each unit's map.

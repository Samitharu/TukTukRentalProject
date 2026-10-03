<?php

declare(strict_types=1);

// Core has no public routes of its own — it provides shared middleware,
// the base Blade layout, and helpers consumed by every other module.
// Locale-prefixed public routes are registered per-module by the
// Localization module's route group registrar (see Modules/Localization).

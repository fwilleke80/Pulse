<?php

/**
 * @file check-in-location.php
 * @brief Hidden optional geolocation payload and non-blocking collection status.
 * @author Frank Willeke
 */

declare(strict_types=1);
?>

<input type="hidden" name="location_available" value="0" data-location-available>
<input type="hidden" name="location_latitude" value="" data-location-latitude>
<input type="hidden" name="location_longitude" value="" data-location-longitude>
<input type="hidden" name="location_accuracy" value="" data-location-accuracy>
<input type="hidden" name="location_address" value="" data-location-address>
<small class="check-in-location-status" data-location-status hidden aria-live="polite"></small>

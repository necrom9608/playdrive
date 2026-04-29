<?php

/*
 * Vooraf gedefinieerde amenities voor de publieke venuepagina.
 *
 * Een venue kan deze keys aan- of uitvinken in het Portal.
 * Optioneel kan er extra context in 'value' (bv. 'Gratis op parking achteraan').
 *
 * Houd deze lijst beheerd vanuit één plek; voeg keys alleen toe aan het einde
 * zodat bestaande amenity-records met die key blijven werken.
 */

return [
    'parking' => [
        'label' => 'Parking',
    ],
    'wheelchair_accessible' => [
        'label' => 'Toegankelijk voor rolstoel',
    ],
    'wifi' => [
        'label' => 'Gratis wifi',
    ],
    'food' => [
        'label' => 'Eten',
    ],
    'drinks' => [
        'label' => 'Drank',
    ],
    'groups_welcome' => [
        'label' => 'Groepen welkom',
    ],
    'kids_friendly' => [
        'label' => 'Kindvriendelijk',
    ],
    'birthday_packages' => [
        'label' => 'Verjaardagspakketten',
    ],
    'corporate_events' => [
        'label' => 'Bedrijfsevents',
    ],
    'private_rental' => [
        'label' => 'Privé-afhuur',
    ],
];

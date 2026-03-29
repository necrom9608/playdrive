@php
    $greeting = 'Hallo ' . trim($member->first_name . ' ' . $member->last_name) . ',';
@endphp

<p>{{ $greeting }}</p>

@if ($type === 'confirmation')
    <p>Je abonnement werd succesvol geregistreerd.</p>
    <p>Geldig van <strong>{{ optional($member->membership_started_at)->format('d/m/Y') }}</strong> tot en met <strong>{{ optional($member->membership_expires_at)->format('d/m/Y') }}</strong>.</p>
@elseif ($type === 'expiring')
    <p>We willen je laten weten dat je abonnement binnenkort vervalt.</p>
    <p>Je huidige geldigheid loopt tot en met <strong>{{ optional($member->membership_expires_at)->format('d/m/Y') }}</strong>.</p>
@elseif ($type === 'expired')
    <p>Je abonnement is vervallen.</p>
    <p>De vervaldatum was <strong>{{ optional($member->membership_expires_at)->format('d/m/Y') }}</strong>.</p>
@else
    <p>Er is een update over je abonnement.</p>
@endif

<p>Neem gerust contact met ons op als je dit wenst te verlengen of als er gegevens aangepast moeten worden.</p>

<p>Met vriendelijke groet,<br>PlayDrive</p>

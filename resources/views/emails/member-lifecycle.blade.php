@php
    $firstName = $membership->account?->first_name ?? 'Lid';
    $lastName  = $membership->account?->last_name ?? '';
    $fullName  = trim($firstName . ' ' . $lastName);
    $endsAt    = $membership->membership_ends_at?->format('d/m/Y') ?? '—';
    $startsAt  = $membership->membership_starts_at?->format('d/m/Y') ?? '—';
@endphp

<p>Hallo {{ $firstName }},</p>

@if ($type === 'confirmation')
    <p>Je abonnement werd succesvol geregistreerd.</p>
    <p>Geldig van <strong>{{ $startsAt }}</strong> tot en met <strong>{{ $endsAt }}</strong>.</p>
@elseif ($type === 'expiring_14d')
    <p>Je abonnement vervalt over <strong>2 weken</strong>, op <strong>{{ $endsAt }}</strong>.</p>
    <p>Neem contact op als je het wil verlengen.</p>
@elseif ($type === 'expiring_7d')
    <p>Je abonnement vervalt over <strong>1 week</strong>, op <strong>{{ $endsAt }}</strong>.</p>
    <p>Neem snel contact op als je het wil verlengen.</p>
@elseif ($type === 'expired')
    <p>Je abonnement is verlopen op <strong>{{ $endsAt }}</strong>.</p>
    <p>Neem contact op als je het wil heractiveren.</p>
@else
    <p>Er is een update over je abonnement.</p>
@endif

<p>Met vriendelijke groet,<br>PlayDrive</p>

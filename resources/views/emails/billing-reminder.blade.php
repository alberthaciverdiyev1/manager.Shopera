<!DOCTYPE html>
<html lang="az">
<body style="font-family: Arial, sans-serif; color:#111; line-height:1.5;">
    <h2 style="margin:0 0 12px;">
        @if ($stage === 'overdue')
            Abunə ödənişi gecikib
        @else
            Abunə yenilənmə xatırlatması
        @endif
    </h2>

    <p>Salam {{ $owner->name }},</p>

    @if ($stage === 'overdue')
        <p>
            <strong>{{ $subscription->plan?->name }}</strong> planı üzrə abunə ödənişi
            <strong>{{ $subscription->ends_at?->format('d.m.Y') }}</strong> tarixində bitib
            ({{ abs($daysLeft) }} gün əvvəl). Xidmətin davam etməsi üçün ödənişi yeniləyin.
        </p>
    @else
        <p>
            <strong>{{ $subscription->plan?->name }}</strong> planı üzrə abunə
            <strong>{{ $subscription->ends_at?->format('d.m.Y') }}</strong> tarixində bitir
            ({{ $daysLeft }} gün qalıb). Xidmətin kəsilməməsi üçün vaxtında yeniləyin.
        </p>
    @endif

    <p style="margin:16px 0;">
        <strong>Plan:</strong> {{ $subscription->plan?->name }}<br>
        <strong>Qiymət:</strong> {{ number_format((float) $subscription->price, 2) }} {{ $subscription->plan?->currency }}<br>
        <strong>Bitmə tarixi:</strong> {{ $subscription->ends_at?->format('d.m.Y') ?? '—' }}
    </p>

    <p>Hörmətlə,<br>ShopEra komandası</p>
</body>
</html>

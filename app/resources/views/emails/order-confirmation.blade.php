<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>تأكيد الطلب</title></head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Tahoma,Arial,sans-serif;color:#0f172a;">
<div style="max-width:560px;margin:24px auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
    <div style="background:#2563eb;color:#ffffff;padding:20px 24px;">
        <h1 style="margin:0;font-size:18px;">سوق التقنية</h1>
        <p style="margin:4px 0 0;font-size:12px;opacity:.9;">تأكيد استلام الطلب</p>
    </div>
    <div style="padding:24px;">
        <p style="font-size:14px;">مرحباً {{ $order['name'] }}،</p>
        <p style="font-size:13px;line-height:1.8;">
            استلمنا طلبك رقم <strong>#{{ $order['order_id'] }}</strong> وهو الآن قيد المعالجة.
            سنرسل لك تحديثاً فور تغيّر حالة الطلب.
        </p>
        <table style="width:100%;border-collapse:collapse;font-size:12px;margin:16px 0;">
            <thead>
            <tr style="background:#f1f5f9;">
                <th style="padding:8px;text-align:right;border:1px solid #e2e8f0;">المنتج</th>
                <th style="padding:8px;border:1px solid #e2e8f0;">الكمية</th>
                <th style="padding:8px;border:1px solid #e2e8f0;">سعر القطعة</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($order['items'] as $item)
                <tr>
                    <td style="padding:8px;border:1px solid #e2e8f0;">{{ $item['name'] }}</td>
                    <td style="padding:8px;border:1px solid #e2e8f0;text-align:center;">{{ $item['quantity'] }}</td>
                    <td style="padding:8px;border:1px solid #e2e8f0;text-align:center;">${{ $item['unit_price'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p style="font-size:14px;font-weight:bold;">المجموع الإجمالي: ${{ $order['total'] }}</p>
        <p style="font-size:11px;color:#64748b;">تاريخ الطلب: {{ $order['placed_at'] }}</p>
    </div>
    <div style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;">
        رسالة آلية من سوق التقنية — مشروع مقرر تطبيقات الإنترنت.
    </div>
</div>
</body>
</html>

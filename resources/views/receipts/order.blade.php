@php
    $subtotal = (float) $order->items->sum(fn ($item) => (float) $item->line_total);
    $total = (float) $order->total;
    $paidWith = $order->payment_method === 'momo' ? 'MOBILE MONEY' : 'CASH';
    $receiptDate = ($order->paid_at ?? $order->created_at)->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->code }}</title>

    <style>
        @font-face {
            font-family: 'Urbanist';
            src: url('{{ asset('assets/fonts/Urbanist/static/Urbanist-Regular.ttf') }}') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Urbanist';
            src: url('{{ asset('assets/fonts/Urbanist/static/Urbanist-Medium.ttf') }}') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Urbanist';
            src: url('{{ asset('assets/fonts/Urbanist/static/Urbanist-SemiBold.ttf') }}') format('truetype');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'Urbanist';
            src: url('{{ asset('assets/fonts/Urbanist/static/Urbanist-Bold.ttf') }}') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; padding: 0; }

        body {
            font-family: 'Urbanist', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ececec;
            color: #111827;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Screen-only toolbar */
        .toolbar {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            padding: 18px 4mm 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .toolbar a,
        .toolbar button {
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            padding: 9px 14px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            line-height: 1.2;
        }

        .toolbar .ghost { background: #fff; color: #374151; border-color: #d1d5db; }
        .toolbar .primary { background: #111827; color: #fff; }

        .stage {
            display: flex;
            justify-content: center;
            padding: 20px 12px 48px;
        }

        .receipt {
            width: 80mm;
            max-width: 100%;
            filter: drop-shadow(0 10px 24px rgba(0, 0, 0, .14));
        }

        /* Perforated paper edges (screen only; invisible on white print paper) */
        .receipt-edge {
            height: 6px;
            background-repeat: repeat-x;
            background-size: 12px 6px;
        }
        .receipt-edge--top {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='6' viewBox='0 0 12 6'><path d='M0 6 L6 0 L12 6 Z' fill='%23ffffff'/></svg>");
        }
        .receipt-edge--bottom {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='6' viewBox='0 0 12 6'><path d='M0 0 L6 6 L12 0 Z' fill='%23ffffff'/></svg>");
        }

        .receipt-body {
            background: #fff;
            padding: 7mm 4.5mm 8mm;
        }

        .brand { text-align: center; }
        .brand-logo {
            width: 25mm;
            height: 25mm;
            object-fit: contain;
            display: inline-block;
        }

        .meta {
            margin-top: 7mm;
            display: grid;
            gap: 2.4mm;
        }
        .meta-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 4mm;
        }
        .meta-label {
            font-size: 8.5px;
            font-weight: 500;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .meta-value {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #111827;
            text-align: right;
            word-break: break-word;
        }

        .rule {
            margin: 5mm 0;
            border: 0;
            border-top: 1px dashed #d1d5db;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .items thead th {
            font-size: 8.5px;
            font-weight: 600;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: #6b7280;
            text-align: left;
            padding: 0 0 3mm;
        }
        .items tbody td {
            font-size: 11px;
            color: #111827;
            vertical-align: top;
            padding: 2.4mm 0;
        }
        .items .c-item { width: 44%; text-transform: uppercase; font-weight: 500; padding-right: 2mm; }
        .items .c-qty { width: 9%; text-align: center; }
        .items .c-price { width: 24%; text-align: right; white-space: nowrap; padding-left: 2.5mm; }
        .items .c-amount { width: 23%; text-align: right; white-space: nowrap; padding-left: 2.5mm; }
        .items .c-qty,
        .items .c-price { color: #374151; }
        .items tbody .c-price,
        .items tbody .c-amount { font-size: 10.5px; }

        .totals {
            margin-top: 5mm;
            display: grid;
            gap: 2.4mm;
        }
        .total-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 4mm;
            font-size: 11px;
        }
        .total-row .label {
            font-size: 8.5px;
            font-weight: 500;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: #6b7280;
        }
        .total-row .value { color: #111827; white-space: nowrap; }
        .total-row--muted .value { color: #9ca3af; }
        .total-row--grand {
            margin-top: 2mm;
            padding-top: 3mm;
            border-top: 1px solid #e5e7eb;
            align-items: center;
        }
        .total-row--grand .label {
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }
        .total-row--grand .value {
            font-size: 17px;
            font-weight: 700;
            color: #111827;
        }

        .thanks { margin-top: 7mm; text-align: center; }
        .thanks p {
            margin: 0;
            font-size: 8.5px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #9ca3af;
        }
        .thanks .served-by {
            margin-bottom: 2mm;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: .12em;
            color: #6b7280;
        }
        .thanks .thanks-code {
            margin-top: 1.5mm;
            font-size: 8px;
            letter-spacing: .18em;
            color: #d1d5db;
        }

        /* Fallback page size; the script below tightens the height to the receipt content. */
        @page { size: 80mm 297mm; margin: 0; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .stage { display: block; padding: 0; }
            .receipt {
                width: 80mm;
                max-width: 80mm;
                filter: none;
            }
            .receipt-edge { display: none; }
            .receipt-body { padding: 5mm 4mm 6mm; }
            .items tbody tr { break-inside: avoid; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    {{-- The script below fills this with an @page height that matches the receipt. --}}
    <style id="receipt-page-size"></style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('orders') }}" class="ghost">&larr; Back to orders</a>
        <button type="button" class="primary" onclick="window.print()">Print receipt</button>
    </div>

    <main class="stage">
        <div class="receipt">
            <div class="receipt-edge receipt-edge--top"></div>

            <div class="receipt-body">
                <header class="brand">
                    <img src="{{ asset('assets/white-logo.png') }}" alt="Trendy" class="brand-logo">
                </header>

                <section class="meta">
                    <div class="meta-row">
                        <span class="meta-label">Order</span>
                        <span class="meta-value">{{ $order->code }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Name</span>
                        <span class="meta-value">{{ mb_strtoupper($order->customer_name) }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Date</span>
                        <span class="meta-value">{{ $receiptDate }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Paid with</span>
                        <span class="meta-value">{{ $paidWith }}</span>
                    </div>
                </section>

                <hr class="rule">

                <table class="items">
                    <thead>
                        <tr>
                            <th class="c-item">Item</th>
                            <th class="c-qty">Qty</th>
                            <th class="c-price">Price</th>
                            <th class="c-amount">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="c-item">{{ mb_strtoupper($item->product_name) }}</td>
                                <td class="c-qty">{{ $item->quantity }}</td>
                                <td class="c-price">GH₵ {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="c-amount">GH₵ {{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <section class="totals">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value">GH₵ {{ number_format($subtotal, 2) }}</span>
                    </div>
                    <div class="total-row total-row--muted">
                        <span class="label">Taxes</span>
                        <span class="value">&mdash;</span>
                    </div>
                    <div class="total-row total-row--muted">
                        <span class="label">Additional fee</span>
                        <span class="value">&mdash;</span>
                    </div>
                    <div class="total-row total-row--grand">
                        <span class="label">Total</span>
                        <span class="value">GH₵ {{ number_format($total, 2) }}</span>
                    </div>
                </section>

                <footer class="thanks">
                    <p class="served-by">Served by {{ mb_strtoupper($order->taken_by_name) }}</p>
                    <p>Thank you for your patronage</p>
                    <p class="thanks-code">{{ $order->code }}</p>
                </footer>
            </div>

            <div class="receipt-edge receipt-edge--bottom"></div>
        </div>
    </main>

    <script>
        // Size the printed page to the receipt so it prints 80mm wide and exactly as long as it
        // needs to be (thermal roll), instead of a fixed sheet with blank space or a second page.
        (function () {
            var receipt = document.querySelector('.receipt');
            var pageStyle = document.getElementById('receipt-page-size');

            if (!receipt || !pageStyle) {
                return;
            }

            function fitPage() {
                var heightMm = receipt.getBoundingClientRect().height * 25.4 / 96;
                heightMm = Math.max(120, Math.ceil(heightMm + 6));
                pageStyle.textContent = '@page { size: 80mm ' + heightMm + 'mm; margin: 0; }';
            }

            window.addEventListener('load', fitPage);
            window.addEventListener('beforeprint', fitPage);

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(fitPage);
            }
        })();
    </script>
</body>
</html>

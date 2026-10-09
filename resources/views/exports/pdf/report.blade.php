<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 1.2cm 1.2cm 1.5cm 1.2cm;
            size: a4 portrait;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #1e293b;
            line-height: 1.35;
        }
        /* Kop Surat Resmi UAD */
        .header-kop {
            border-bottom: 2px solid #059669;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title-box {
            text-align: center;
        }
        .inst-top {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .inst-sub {
            font-size: 13pt;
            font-weight: 800;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1px;
        }
        .inst-desc {
            font-size: 8pt;
            color: #64748b;
            margin-top: 2px;
        }
        /* Dokumen Title */
        .doc-title-box {
            text-align: center;
            margin: 12px 0 10px 0;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .meta-table {
            width: 100%;
            font-size: 8pt;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2px 4px;
        }
        .meta-label {
            color: #64748b;
            width: 15%;
            font-weight: 600;
        }
        .meta-val {
            color: #1e293b;
            font-weight: bold;
            width: 35%;
        }

        /* KPI Box */
        .kpi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .kpi-cell {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 6px 10px;
            text-align: center;
        }
        .kpi-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            font-weight: bold;
            color: #166534;
            letter-spacing: 0.3px;
        }
        .kpi-value {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #059669;
            color: #ffffff;
            font-size: 8pt;
            font-weight: bold;
            text-align: left;
            padding: 6px 6px;
            border: 1px solid #059669;
            text-transform: uppercase;
        }
        .data-table td {
            font-size: 8pt;
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }

        /* Tanda Tangan */
        .signature-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .sig-space {
            height: 50px;
        }
        .sig-name {
            font-weight: bold;
            text-decoration: underline;
            color: #0f172a;
        }
        .sig-role {
            font-size: 7.5pt;
            color: #64748b;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #94a3b8;
            text-align: right;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <!-- Header / Kop Surat -->
    <div class="header-kop">
        <table class="header-table">
            <tr>
                <td class="header-title-box">
                    <div class="inst-top">UNIVERSITAS AHMAD DAHLAN (UAD)</div>
                    <div class="inst-sub">PUSAT PENGELOLAAN SAMPAH MANDIRI (PS2 UAD)</div>
                    <div class="inst-desc">Sistem Informasi Zero Waste Campus &bull; Pengelolaan Sampah Terpadu 3R &bull; Yogyakarta</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Judul Dokumen -->
    <div class="doc-title-box">
        <div class="doc-title">{{ $title }}</div>
    </div>

    <!-- Meta Info -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">Unit Kampus</td>
            <td class="meta-val">: {{ $campusName }}</td>
            <td class="meta-label">Waktu Cetak</td>
            <td class="meta-val">: {{ $printedAt }} WIB</td>
        </tr>
        <tr>
            <td class="meta-label">Periode Data</td>
            <td class="meta-val">: {{ $dateFrom }} s/d {{ $dateTo }}</td>
            <td class="meta-label">Dicetak Oleh</td>
            <td class="meta-val">: {{ $printedBy }}</td>
        </tr>
    </table>

    <!-- Ringkasan KPI -->
    @if(!empty($metrics))
        <table class="kpi-table">
            <tr>
                @foreach($metrics as $label => $val)
                    <td class="kpi-cell">
                        <div class="kpi-label">{{ $label }}</div>
                        <div class="kpi-value">{{ $val }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <!-- Tabel Data Sesuai Jenis Laporan -->
    <table class="data-table">
        @if($type === 'weighing')
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 15%;">Unit Kampus</th>
                    <th style="width: 15%;">Sumber</th>
                    <th style="width: 33%;">Rincian Sampah</th>
                    <th style="width: 10%;" class="text-right">Berat (kg)</th>
                    <th style="width: 10%;">Petugas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $idx => $r)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $r['date'] }}</td>
                        <td>{{ $r['campus'] }}</td>
                        <td>{{ $r['source'] }}</td>
                        <td style="font-size: 7.5pt;">{{ $r['details'] }}</td>
                        <td class="text-right font-mono">{{ number_format($r['weight_kg'], 1, ',', '.') }}</td>
                        <td>{{ $r['officer'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 15px; color: #94a3b8;">Tidak ada data penimbangan pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>

        @elseif($type === 'sales')
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 12%;">No Faktur</th>
                    <th style="width: 11%;">Tanggal</th>
                    <th style="width: 14%;">Kampus</th>
                    <th style="width: 15%;">Pengepul</th>
                    <th style="width: 25%;">Rincian Penjualan</th>
                    <th style="width: 8%;" class="text-right">Berat (kg)</th>
                    <th style="width: 10%;" class="text-right">Total (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $idx => $r)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="font-mono">{{ $r['invoice'] }}</td>
                        <td>{{ $r['date'] }}</td>
                        <td>{{ $r['campus'] }}</td>
                        <td>{{ $r['buyer'] }}</td>
                        <td style="font-size: 7.5pt;">{{ $r['details'] }}</td>
                        <td class="text-right font-mono">{{ number_format($r['weight_kg'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono">{{ number_format($r['total_amount'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 15px; color: #94a3b8;">Tidak ada data penjualan pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>

        @elseif($type === 'pickups')
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 15%;">Unit Kampus</th>
                    <th style="width: 20%;">Vendor Pengangkut</th>
                    <th style="width: 20%;">Driver & Kendaraan</th>
                    <th style="width: 13%;" class="text-right">Residu (kg)</th>
                    <th style="width: 15%;" class="text-right">Biaya (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $idx => $r)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $r['date'] }}</td>
                        <td>{{ $r['campus'] }}</td>
                        <td>{{ $r['vendor'] }}</td>
                        <td>{{ $r['driver'] }}</td>
                        <td class="text-right font-mono">{{ number_format($r['volume_kg'], 1, ',', '.') }}</td>
                        <td class="text-right font-mono">{{ number_format($r['total_cost'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 15px; color: #94a3b8;">Tidak ada catatan pengangkutan pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>

        @elseif($type === 'finance')
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 12%;">Tanggal</th>
                    <th style="width: 13%;">Kampus</th>
                    <th style="width: 12%;">Jenis</th>
                    <th style="width: 13%;">Kategori</th>
                    <th style="width: 23%;">Keterangan</th>
                    <th style="width: 11%;" class="text-right">Debet (Rp)</th>
                    <th style="width: 11%;" class="text-right">Kredit (Rp)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $idx => $r)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $r['date'] }}</td>
                        <td>{{ $r['campus'] }}</td>
                        <td>{{ $r['jenis'] }}</td>
                        <td>{{ $r['sumber'] }}</td>
                        <td style="font-size: 7.5pt;">{{ $r['keterangan'] }}</td>
                        <td class="text-right font-mono">{{ $r['debet'] > 0 ? number_format($r['debet'], 0, ',', '.') : '-' }}</td>
                        <td class="text-right font-mono">{{ $r['kredit'] > 0 ? number_format($r['kredit'], 0, ',', '.') : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center" style="padding: 15px; color: #94a3b8;">Tidak ada mutasi kas pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>

        @elseif($type === 'kap')
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">No</th>
                    <th style="width: 11%;">Tanggal</th>
                    <th style="width: 14%;">Kampus</th>
                    <th style="width: 12%;">Civitas</th>
                    <th style="width: 18%;">Fakultas/Unit</th>
                    <th style="width: 8%;" class="text-center">K</th>
                    <th style="width: 8%;" class="text-center">A</th>
                    <th style="width: 8%;" class="text-center">P</th>
                    <th style="width: 8%;" class="text-center">Total</th>
                    <th style="width: 8%;" class="text-center">Kategori</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $idx => $r)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td>{{ $r['date'] }}</td>
                        <td>{{ $r['campus'] }}</td>
                        <td>{{ $r['role'] }}</td>
                        <td style="font-size: 7.5pt;">{{ $r['faculty'] }}</td>
                        <td class="text-center font-mono">{{ $r['knowledge'] }}</td>
                        <td class="text-center font-mono">{{ $r['attitude'] }}</td>
                        <td class="text-center font-mono">{{ $r['practice'] }}</td>
                        <td class="text-center font-mono font-bold">{{ $r['overall'] }}</td>
                        <td class="text-center">{{ $r['category'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center" style="padding: 15px; color: #94a3b8;">Tidak ada data survei KAP pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        @endif
    </table>

    <!-- Bagian Tanda Tangan Resmi -->
    <table class="signature-table">
        <tr>
            <td>
                <div>Petugas / Operator TPS3R UAD,</div>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $printedBy }}</div>
                <div class="sig-role">Operator Sistem PS2 UAD</div>
            </td>
            <td>
                <div>Yogyakarta, {{ date('d F Y') }}<br>Mengetahui & Menyetujui,</div>
                <div class="sig-space"></div>
                <div class="sig-name">Koordinator PS2 UAD</div>
                <div class="sig-role">Pusat Pengelolaan Sampah Mandiri UAD</div>
            </td>
        </tr>
    </table>

    <!-- Footer Nomor Halaman -->
    <div class="footer">
        Dokumen dicetak secara elektronik melalui Sistem Informasi PS2 UAD pada {{ $printedAt }} WIB.
    </div>

</body>
</html>


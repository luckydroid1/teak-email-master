import os
import sys
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import cm, mm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, KeepTogether, HRFlowable
)
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super().showPage()
        super().save()

    def draw_page_decorations(self, page_count):
        self.saveState()
        self.setFont("Helvetica", 8)
        self.setFillColor(colors.HexColor("#64748b"))
        
        # Header (Top)
        self.drawString(1.5 * cm, A4[1] - 1.2 * cm, "Teak Email — Architecture & Infrastructure Specification")
        self.drawRightString(A4[0] - 1.5 * cm, A4[1] - 1.2 * cm, "Confidential / Engineering Summary")
        self.setStrokeColor(colors.HexColor("#e2e8f0"))
        self.setLineWidth(0.5)
        self.line(1.5 * cm, A4[1] - 1.35 * cm, A4[0] - 1.5 * cm, A4[1] - 1.35 * cm)

        # Footer (Bottom)
        self.line(1.5 * cm, 1.35 * cm, A4[0] - 1.5 * cm, 1.35 * cm)
        self.drawString(1.5 * cm, 1.0 * cm, "Teak Email Platform | Prepared for Infrastructure Team")
        self.drawRightString(A4[0] - 1.5 * cm, 1.0 * cm, f"Halaman {self._pageNumber} dari {page_count}")
        self.restoreState()

def build_pdf(filename):
    doc = SimpleDocTemplate(
        filename,
        pagesize=A4,
        leftMargin=1.5 * cm,
        rightMargin=1.5 * cm,
        topMargin=1.8 * cm,
        bottomMargin=1.8 * cm
    )

    styles = getSampleStyleSheet()
    
    # Custom styles
    title_style = ParagraphStyle(
        'DocTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=20,
        leading=24,
        textColor=colors.HexColor('#0f172a'),
        spaceAfter=4
    )
    
    subtitle_style = ParagraphStyle(
        'DocSubtitle',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=10,
        leading=14,
        textColor=colors.HexColor('#475569'),
        spaceAfter=12
    )

    h1_style = ParagraphStyle(
        'Heading1_Custom',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=12,
        leading=16,
        textColor=colors.HexColor('#0f172a'),
        spaceBefore=10,
        spaceAfter=6,
        keepWithNext=True
    )

    body_style = ParagraphStyle(
        'Body_Custom',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=9,
        leading=13,
        textColor=colors.HexColor('#334155')
    )

    body_bold = ParagraphStyle(
        'Body_Bold',
        parent=body_style,
        fontName='Helvetica-Bold'
    )

    table_header = ParagraphStyle(
        'TableHeader',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=8.5,
        leading=11,
        textColor=colors.HexColor('#0f172a')
    )

    table_cell = ParagraphStyle(
        'TableCell',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8,
        leading=11,
        textColor=colors.HexColor('#334155')
    )

    table_cell_bold = ParagraphStyle(
        'TableCellBold',
        parent=table_cell,
        fontName='Helvetica-Bold',
        textColor=colors.HexColor('#0f172a')
    )

    code_style = ParagraphStyle(
        'CodeStyle',
        parent=styles['Normal'],
        fontName='Courier',
        fontSize=7.5,
        leading=10,
        textColor=colors.HexColor('#0f172a')
    )

    story = []

    # Title block
    story.append(Paragraph("TEAK EMAIL — RINGKASAN ARSITEKTUR & KEBUTUHAN SERVER", title_style))
    story.append(Paragraph("Dokumen Spesifikasi Teknis, Kebutuhan Resource VPS, dan Arsitektur Sistem", subtitle_style))
    story.append(HRFlowable(width="100%", thickness=1.5, color=colors.HexColor('#0284c7'), spaceAfter=10))

    # 1. Ringkasan Eksekutif
    story.append(Paragraph("1. Ringkasan Eksekutif", h1_style))
    story.append(Paragraph(
        "<b>Teak Email</b> adalah platform infrastruktur email penerima (<i>receive-only disposable & persistent inboxes</i>) "
        "yang dirancang untuk developer dan AI Agent. Aplikasi ini memungkinkan pembuatan inbox instan, manajemen custom domain, "
        "penerimaan pesan otomatis, ekstraksi kode OTP secara real-time melalui REST API serta integrasi AI Agent via <b>Model Context Protocol (MCP)</b>.",
        body_style
    ))
    story.append(Spacer(1, 8))

    # 2. Tech Stack
    story.append(Paragraph("2. Tech Stack & Komponen Perangkat Lunak", h1_style))
    
    tech_data = [
        [Paragraph("Komponen / Layer", table_header), Paragraph("Teknologi / Runtime", table_header), Paragraph("Fungsi & Peran", table_header)],
        [Paragraph("<b>Web & REST API</b>", table_cell_bold), Paragraph("PHP 8.x (Native + PDO)", table_cell), Paragraph("Routing, dashboard UI, autentikasi user, REST API endpoint (/api/inboxes), credit ledger, OTP parser.", table_cell)],
        [Paragraph("<b>Web Server</b>", table_cell_bold), Paragraph("Nginx (FastCGI)", table_cell), Paragraph("Reverse proxy, TLS/SSL termination, FastCGI router ke PHP-FPM, static asset caching.", table_cell)],
        [Paragraph("<b>Database</b>", table_cell_bold), Paragraph("MariaDB / MySQL 8.x", table_cell), Paragraph("Penyimpanan data relasional: users, API keys, inboxes, OTP records, audit log, credit balance.", table_cell)],
        [Paragraph("<b>AI MCP Server</b>", table_cell_bold), Paragraph("Node.js 20.x (@modelcontextprotocol/sdk)", table_cell), Paragraph("Daemon background service untuk menghubungkan AI Agents (Claude, Cursor, GPT) ke inboxes via MCP.", table_cell)],
        [Paragraph("<b>Mail Server (MTA/MDA)</b>", table_cell_bold), Paragraph("Mailcow / Postfix + Dovecot", table_cell), Paragraph("Inbound SMTP listener (port 25), penyimpanan mailbox format Maildir, pengelolaan queue email.", table_cell)],
        [Paragraph("<b>Edge & Security</b>", table_cell_bold), Paragraph("Cloudflare (DNS & Tunnel/Proxy)", table_cell), Paragraph("DNS Anycast, perlindungan DDoS, SSL certs, dan Cloudflare Tunnel (cloudflared) ke VPS.", table_cell)]
    ]

    avail_w = A4[0] - 3.0 * cm
    t_tech = Table(tech_data, colWidths=[3.2 * cm, 4.2 * cm, avail_w - 7.4 * cm])
    t_tech.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#f1f5f9')),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#cbd5e1')),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
    ]))
    story.append(t_tech)
    story.append(Spacer(1, 10))

    # 3. Kebutuhan Resource VPS
    story.append(Paragraph("3. Spesifikasi Kebutuhan Server (VPS Resource Requirement)", h1_style))
    
    spec_data = [
        [Paragraph("Parameter", table_header), Paragraph("Rekomendasi (Production Ready)", table_header), Paragraph("Minimal (QA / Testing Saja)", table_header)],
        [Paragraph("<b>Sistem Operasi (OS)</b>", table_cell_bold), Paragraph("Ubuntu 22.04 LTS / 24.04 LTS (64-bit)", table_cell), Paragraph("Ubuntu 22.04 LTS (64-bit)", table_cell)],
        [Paragraph("<b>Processor (CPU)</b>", table_cell_bold), Paragraph("<b>2 vCPU / 2 Core</b> (x86_64 atau ARM64)", table_cell), Paragraph("1 vCPU / 1 Core", table_cell)],
        [Paragraph("<b>Memory (RAM)</b>", table_cell_bold), Paragraph("<b>4 GB RAM</b> (disertai 2 GB Swap)", table_cell), Paragraph("2 GB RAM (+ wajib 2 GB Swap)", table_cell)],
        [Paragraph("<b>Storage / Disk</b>", table_cell_bold), Paragraph("<b>40 – 50 GB SSD / NVMe</b>", table_cell), Paragraph("20 – 30 GB SSD", table_cell)],
        [Paragraph("<b>Jaringan & IP</b>", table_cell_bold), Paragraph("1x Dedicated Public IPv4 + Unmetered / 1TB BW", table_cell), Paragraph("1x Dedicated Public IPv4", table_cell)],
        [Paragraph("<b>Port yang Terbuka</b>", table_cell_bold), Paragraph("22 (SSH), 80 (HTTP), 443 (HTTPS), 25 (SMTP Inbound)", table_cell), Paragraph("22 (SSH), 80 (HTTP), 443 (HTTPS)", table_cell)]
    ]

    t_spec = Table(spec_data, colWidths=[3.5 * cm, 7.5 * cm, avail_w - 11.0 * cm])
    t_spec.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), colors.HexColor('#e0f2fe')),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.HexColor('#cbd5e1')),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
    ]))
    story.append(t_spec)
    story.append(Spacer(1, 8))

    story.append(Paragraph(
        "<i>*Catatan: RAM 4 GB direkomendasikan agar stack Mail Server (Dovecot/Postfix queue), database MariaDB, PHP-FPM, "
        "dan Node.js background process dapat beroperasi secara simultan tanpa risiko Out-Of-Memory (OOM).</i>",
        ParagraphStyle('Note', parent=body_style, fontSize=7.5, leading=10, textColor=colors.HexColor('#64748b'))
    ))
    story.append(Spacer(1, 10))

    # 4. Diagram Arsitektur & Alur Data
    story.append(Paragraph("4. Diagram Arsitektur Sistem & Alur Kerja", h1_style))
    
    diagram_text = (
        "                    [ Web Dashboard User ]            [ AI Agent (Cursor / Claude / GPT) ]\n"
        "                              │                                         │\n"
        "                              │ (HTTPS Web UI)                          │ (JSON-RPC / REST Auth)\n"
        "                              ▼                                         ▼\n"
        "                  ┌───────────────────────────────────────────────────────────────┐\n"
        "                  │            Cloudflare Edge (DNS, Proxy & CDN Layer)           │\n"
        "                  └───────────────────────────────┬───────────────────────────────┘\n"
        "                                                  │ (Cloudflare Tunnel / HTTPS)\n"
        "                                                  ▼\n"
        "                                   ┌──────────────────────────────┐\n"
        "                                   │    Nginx Web Server / Proxy  │\n"
        "                                   └──────────────┬───────────────┘\n"
        "                          ┌───────────────────────┴───────────────────────┐\n"
        "                          ▼                                               ▼\n"
        "               ┌───────────────────────┐                       ┌───────────────────────┐\n"
        "               │   PHP-FPM 8.x Engine  │                       │  Node.js 20 MCP Svc   │\n"
        "               │  (Web UI & REST API)  │                       │ (AI Agent Connector)  │\n"
        "               └──────────┬────────────┘                       └──────────┬────────────┘\n"
        "                          │                                               │\n"
        "                          ├───────────────────────┬───────────────────────┘\n"
        "                          ▼                       ▼\n"
        "               ┌───────────────────────┐ ┌───────────────────────┐\n"
        "               │  MariaDB / MySQL DB   │ │ Postfix/Dovecot (MDA) │ ◄── [ Inbound Email / OTP ]\n"
        "               │  (Auth, Inboxes, OTP) │ │  (Maildir: /var/vmail)│     (MX Record / Port 25)\n"
        "               └───────────────────────┘ └───────────────────────┘"
    )

    t_diag = Table([[Paragraph(f"<pre>{diagram_text}</pre>", code_style)]], colWidths=[avail_w])
    t_diag.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor('#0f172a')),
        ('TEXTCOLOR', (0, 0), (-1, -1), colors.HexColor('#f8fafc')),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
        ('LEFTPADDING', (0, 0), (-1, -1), 10),
        ('RIGHTPADDING', (0, 0), (-1, -1), 10),
    ]))
    story.append(t_diag)
    story.append(Spacer(1, 10))

    # 5. Keamanan & Skalabilitas
    story.append(Paragraph("5. Standar Keamanan & Proteksi Anti-Abuse", h1_style))
    
    sec_points = [
        "<b>Autentikasi & Password:</b> Enkripsi password menggunakan <code>bcrypt</code> standard industri.",
        "<b>API Key Management:</b> API key berformat <code>cib_...</code> dengan enkripsi satu arah SHA-256 hash di database.",
        "<b>Session Security:</b> Cookie sesi dikonfigurasi dengan flag <code>Secure; HttpOnly; SameSite=Strict</code>.",
        "<b>Database Protection:</b> Semua interaksi query menggunakan PDO Prepared Statements untuk mencegah SQL Injection.",
        "<b>Anti-Spam & Abuse:</b> Dilengkapi proteksi form honeypot, rate limiting per user/jam, dan trust tiers escalation."
    ]
    for pt in sec_points:
        story.append(Paragraph(f"• {pt}", ParagraphStyle('Bullet', parent=body_style, leftIndent=10, spaceAfter=3)))

    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"PDF successfully generated: {filename}")

if __name__ == '__main__':
    out_path = os.path.abspath("Teak-Email-Architecture-Summary.pdf")
    build_pdf(out_path)

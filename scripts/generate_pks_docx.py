import zipfile
import html
import re

def create_docx(filename):
    content_types = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>"""

    rels = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

    doc_rels = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>"""

    styles = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault>
      <w:rPr>
        <w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/>
        <w:sz w:val="22"/>
        <w:color w:val="1E293B"/>
      </w:rPr>
    </w:rPrDefault>
    <w:pPrDefault>
      <w:pPr>
        <w:spacing w:after="140" w:line="276" w:lineRule="auto"/>
      </w:pPr>
    </w:pPrDefault>
  </w:docDefaults>
</w:styles>"""

    # Helper functions for OpenXML elements
    def p(text="", align=None, bold=False, italic=False, size=22, space_after=140, color="1E293B", underline=False):
        p_pr = f'<w:pPr><w:spacing w:after="{space_after}" w:line="276" w:lineRule="auto"/>'
        if align:
            p_pr += f'<w:jc w:val="{align}"/>'
        p_pr += '</w:pPr>'
        
        if not text:
            return f'<w:p>{p_pr}</w:p>'
            
        r_pr = f'<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="{size}"/><w:color w:val="{color}"/>'
        if bold: r_pr += '<w:b/>'
        if italic: r_pr += '<w:i/>'
        if underline: r_pr += '<w:u w:val="single"/>'
        r_pr += '</w:rPr>'
        
        # Handle simple bold tags **text** or <u>text</u>
        parts = re.split(r'(\*\*.*?\*\*|<u>.*?</u>)', text)
        runs = []
        for part in parts:
            if not part: continue
            curr_b = bold
            curr_u = underline
            curr_txt = part
            if part.startswith('**') and part.endswith('**'):
                curr_b = True
                curr_txt = part[2:-2]
            elif part.startswith('<u>') and part.endswith('</u>'):
                curr_u = True
                curr_txt = part[3:-4]
            
            this_r_pr = f'<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="{size}"/><w:color w:val="{color}"/>'
            if curr_b: this_r_pr += '<w:b/>'
            if italic: this_r_pr += '<w:i/>'
            if curr_u: this_r_pr += '<w:u w:val="single"/>'
            this_r_pr += '</w:rPr>'
            runs.append(f'<w:r>{this_r_pr}<w:t xml:space="preserve">{html.escape(curr_txt)}</w:t></w:r>')
            
        return f'<w:p>{p_pr}{"".join(runs)}</w:p>'

    def heading(text, level=1):
        if level == 1:
            return p(text, align="center", bold=True, size=30, space_after=60, color="0F172A")
        elif level == 2:
            return p(text, align="center", bold=True, size=24, space_after=80, color="1E3A8A")
        elif level == 3:
            return p(text, align="left", bold=True, size=23, space_after=100, color="0F172A")
        return p(text, bold=True, size=22)

    def table(headers, rows, col_widths=None):
        out = ['<w:tbl>']
        out.append("""<w:tblPr>
          <w:tblW w:w="9200" w:type="dxa"/>
          <w:jc w:val="center"/>
          <w:tblBorders>
            <w:top w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
            <w:left w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
            <w:bottom w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
            <w:right w:val="single" w:sz="6" w:space="0" w:color="CBD5E1"/>
            <w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
            <w:insideV w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>
          </w:tblBorders>
          <w:tblCellMar>
            <w:top w:w="140" w:type="dxa"/>
            <w:left w:w="160" w:type="dxa"/>
            <w:bottom w:w="140" w:type="dxa"/>
            <w:right w:w="160" w:type="dxa"/>
          </w:tblCellMar>
        </w:tblPr>""")
        
        # Header Row
        out.append('<w:tr><w:trPr><w:tblHeader/></w:trPr>')
        for idx, h in enumerate(headers):
            w_attr = f'<w:tcW w:w="{col_widths[idx]}" w:type="dxa"/>' if col_widths and idx < len(col_widths) else '<w:tcW w:w="0" w:type="auto"/>'
            out.append(f"""<w:tc>
              <w:tcPr>{w_attr}<w:shd w:val="clear" w:color="auto" w:fill="F1F5F9"/></w:tcPr>
              {p(h, bold=True, size=20, align="center", space_after=40, color="0F172A")}
            </w:tc>""")
        out.append('</w:tr>')
        
        # Body Rows
        for r_idx, row in enumerate(rows):
            shd = 'F8FAFC' if r_idx % 2 == 1 else 'FFFFFF'
            out.append('<w:tr>')
            for c_idx, cell in enumerate(row):
                w_attr = f'<w:tcW w:w="{col_widths[c_idx]}" w:type="dxa"/>' if col_widths and c_idx < len(col_widths) else '<w:tcW w:w="0" w:type="auto"/>'
                
                # Split cell by newline for multi-paragraph cell
                lines = cell.split('\n')
                cell_p_xml = []
                for line in lines:
                    align = "center" if (c_idx == 0 and len(cell) < 15) or "%" in line else "left"
                    is_b = False
                    if line.startswith('• '):
                        line_txt = line
                    elif line.startswith('Termin ') or line.startswith('TOTAL'):
                        is_b = True
                        line_txt = line
                    else:
                        line_txt = line
                    cell_p_xml.append(p(line_txt, size=19, space_after=30, align=align, bold=is_b))
                    
                out.append(f"""<w:tc>
                  <w:tcPr>{w_attr}<w:shd w:val="clear" w:color="auto" w:fill="{shd}"/></w:tcPr>
                  {''.join(cell_p_xml)}
                </w:tc>""")
            out.append('</w:tr>')
            
        out.append('</w:tbl>')
        out.append(p('', space_after=120))
        return ''.join(out)

    def hr():
        return """<w:p>
          <w:pPr>
            <w:pBdr><w:bottom w:val="single" w:sz="12" w:space="8" w:color="0F172A"/></w:pBdr>
            <w:spacing w:after="180"/>
          </w:pPr>
        </w:p>"""

    def signature_table():
        return """<w:tbl>
          <w:tblPr>
            <w:tblW w:w="9200" w:type="dxa"/>
            <w:jc w:val="center"/>
            <w:tblBorders>
              <w:top w:val="none"/>
              <w:left w:val="none"/>
              <w:bottom w:val="none"/>
              <w:right w:val="none"/>
              <w:insideH w:val="none"/>
              <w:insideV w:val="none"/>
            </w:tblBorders>
          </w:tblPr>
          <w:tr>
            <w:tc>
              <w:tcPr><w:tcW w:w="4600" w:type="dxa"/></w:tcPr>
              """ + p("PIHAK PERTAMA", align="center", bold=True, size=21) + """
              """ + p("POLITEKNIK INDONUSA SURAKARTA", align="center", bold=True, size=20) + """
              """ + p("", space_after=400) + """
              """ + p("(Meterai Rp 10.000 & Stempel)", align="center", italic=True, size=18, color="64748B") + """
              """ + p("", space_after=400) + """
              """ + p("<u>[Nama Direktur Kampus]</u>", align="center", bold=True, size=21) + """
              """ + p("Direktur", align="center", size=20) + """
            </w:tc>
            <w:tc>
              <w:tcPr><w:tcW w:w="4600" w:type="dxa"/></w:tcPr>
              """ + p("PIHAK KEDUA", align="center", bold=True, size=21) + """
              """ + p("TIM PENGEMBANG SISTEM RAGI", align="center", bold=True, size=20) + """
              """ + p("", space_after=400) + """
              """ + p("(Meterai Rp 10.000 & Stempel)", align="center", italic=True, size=18, color="64748B") + """
              """ + p("", space_after=400) + """
              """ + p("<u>[Nama Pimpinan Pengembang]</u>", align="center", bold=True, size=21) + """
              """ + p("Lead Architect / Project Manager", align="center", size=20) + """
            </w:tc>
          </w:tr>
        </w:tbl>""" + p("", space_after=200)

    # Assemble Document Content
    body = []
    
    # Title Section
    body.append(heading("SURAT PERJANJIAN KERJA SAMA (PKS)", level=1))
    body.append(heading('PENGADAAN DAN PENGEMBANGAN SISTEM INFORMASI TERPADU "RAGI"', level=2))
    body.append(p("ANTARA", align="center", bold=True, size=20, space_after=40))
    body.append(p("POLITEKNIK INDONUSA SURAKARTA", align="center", bold=True, size=22, space_after=40))
    body.append(p("DENGAN", align="center", bold=True, size=20, space_after=40))
    body.append(p("TIM PENGEMBANG SISTEM INFORMASI RAGI", align="center", bold=True, size=22, space_after=140))
    body.append(hr())
    
    body.append(p("**Nomor Pihak Pertama :** [Nomor Surat Kampus]/PKS/POLTEK-INDO/2026", size=20, space_after=40))
    body.append(p("**Nomor Pihak Kedua   :** [Nomor Surat Developer]/PKS-DEV/RAGI/X/2026", size=20, space_after=160))
    
    body.append(p("Pada hari ini, **[Hari]**, tanggal **[Tanggal]** bulan **[Bulan]** tahun **Dua Ribu Dua Puluh Enam (..-..-2026)**, bertempat di Surakarta, yang bertanda tangan di bawah ini:"))
    
    body.append(p("1. **[Nama Pimpinan / Direktur Kampus]**, bertindak dalam jabatannya selaku Direktur Politeknik Indonusa Surakarta, berkedudukan di Jl. KH Samanhudi No. 84-86, Laweyan, Surakarta, Jawa Tengah, bertindak untuk dan atas nama **POLITEKNIK INDONUSA SURAKARTA**, selanjutnya disebut sebagai **PIHAK PERTAMA**."))
    
    body.append(p("2. **[Nama Pimpinan Pengembang]**, bertindak dalam jabatannya selaku Pimpinan Tim Pengembang / Lead Architect Sistem Informasi RAGI, berkedudukan di [Alamat Kantor Pengembang], bertindak untuk dan atas nama **TIM PENGEMBANG RAGI**, selanjutnya disebut sebagai **PIHAK KEDUA**."))
    
    body.append(p("PIHAK PERTAMA dan PIHAK KEDUA secara bersama-sama selanjutnya disebut sebagai **PARA PIHAK**, dan masing-masing disebut sebagai **PIHAK**."))
    
    body.append(p("PARA PIHAK terlebih dahulu menerangkan hal-hal sebagai berikut:", bold=True))
    body.append(p("1. Bahwa PIHAK PERTAMA memerlukan sistem informasi manajemen kampus terintegrasi untuk mendukung tata kelola akademik, keuangan, kepegawaian, sarana prasarana, dan penerimaan mahasiswa baru di Politeknik Indonusa Surakarta."))
    body.append(p("2. Bahwa PIHAK KEDUA memiliki kompetensi, keahlian, teknologi, dan pengalaman dalam pengembangan sistem informasi perguruan tinggi yang terintegrasi (Sistem RAGI)."))
    body.append(p("3. Bahwa PARA PIHAK telah sepakat atas nilai pengadaan, ruang lingkup pengerjaan per modul, dan jadwal termin pembayaran yang dituangkan dalam Perjanjian ini."))
    
    body.append(p("Berdasarkan hal-hal tersebut di atas, PARA PIHAK sepakat untuk membuat, menandatangani, dan melaksanakan Perjanjian Kerja Sama ini dengan syarat-syarat dan ketentuan sebagai berikut:"))
    
    # Pasal 1
    body.append(heading("PASAL 1 — DEFINISI DAN ISTILAH", level=3))
    body.append(p("Dalam Perjanjian ini yang dimaksud dengan:"))
    body.append(p("1. **Sistem RAGI** adalah ekosistem perangkat lunak terintegrasi berbasis web yang dikembangkan oleh PIHAK KEDUA, mencakup modul SSO/IAM, SIMPEG, SIKEU, SIAKAD, SPMB, dan SINAPRA."))
    body.append(p("2. **Scope of Work (SoW)** adalah batasan detail spesifikasi teknis dan fungsi modul yang disepakati untuk dikembangkan dan diserahkan sebagaimana tercantum dalam **Lampiran I**."))
    body.append(p("3. **Term of Payment (ToP)** adalah ketentuan tahapan pembayaran berdasarkan capaian penyelesaian pekerjaan (milestone) sebagaimana tercantum dalam **Lampiran II**."))
    body.append(p("4. **Berita Acara Serah Terima (BAST)** adalah dokumen bukti tertulis yang ditandatangani oleh PARA PIHAK yang menyatakan bahwa modul atau seluruh sistem telah selesai diuji dan diterima dengan baik."))
    body.append(p("5. **Change Request (CR)** adalah setiap permintaan modifikasi, penambahan, atau perubahan fitur yang berada di luar batasan Scope of Work (SoW) yang telah disepakati."))
    
    # Pasal 2
    body.append(heading("PASAL 2 — RUANG LINGKUP PEKERJAAN", level=3))
    body.append(p("1. PIHAK KEDUA berkewajiban merancang, mengonfigurasi, menguji, dan menyerahkan implementasi Sistem RAGI kepada PIHAK PERTAMA."))
    body.append(p("2. Rincian batasan pengerjaan modul, fitur yang dikerjakan (**In-Scope**), dan fitur yang secara tegas **tidak termasuk (Out-of-Scope)** diatur secara mengikat pada **Lampiran I (Scope of Work)** Perjanjian ini."))
    body.append(p("3. Modul utama yang tercakup dalam kesepakatan ini terdiri atas:"))
    body.append(p("   a. Modul Master SSO & Manajemen Pengguna (IAM & RBAC)"))
    body.append(p("   b. Modul Sistem Informasi Kepegawaian (SIMPEG) & Payroll"))
    body.append(p("   c. Modul Sistem Informasi Keuangan (SIKEU) & Akuntansi Kas"))
    body.append(p("   d. Modul Sistem Informasi Akademik (SIAKAD) & Pemantauan Kurikulum OBE"))
    body.append(p("   e. Modul Seleksi Penerimaan Mahasiswa Baru (SPMB) Jalur Berkas/Administrasi"))
    body.append(p("   f. Modul Sistem Informasi Sarana Prasarana (SINAPRA)"))
    
    # Pasal 3
    body.append(heading("PASAL 3 — JANGKA WAKTU DAN JADWAL PENGERJAAN", level=3))
    body.append(p("1. Pekerjaan pengembangan dan implementasi Sistem RAGI dilaksanakan selama **[Jumlah Hari/Bulan, contoh: 90 (Sembilan Puluh) Hari Kalender / 12 Minggu]**, terhitung sejak ditandatanganinya Perjanjian ini dan diterimanya Pembayaran Uang Muka (Termin 1)."))
    body.append(p("2. Rincian jadwal penyerahan modul (Milestone) per tahap tercantum dalam **Lampiran II**."))
    body.append(p("3. Apabila terjadi keterlambatan yang disebabkan oleh belum diserahkannya data dasar/master dari PIHAK PERTAMA, maka jangka waktu pengerjaan otomatis disesuaikan secara proporsional tanpa penalti bagi PIHAK KEDUA."))
    
    # Pasal 4
    body.append(heading("PASAL 4 — NILAI KONTRAK DAN TERMIN PEMBAYARAN", level=3))
    body.append(p("1. Total nilai pengadaan Sistem RAGI yang disepakati PARA PIHAK adalah sebesar: **Rp [Nominal Angka],- ([Terbilang Rupiah])** (sudah termasuk biaya lisensi penggunaan, instalasi pada infrastruktur kampus, pelatihan admin, dan garansi pemeliharaan)."))
    body.append(p("2. Pembayaran dilakukan secara bertahap (**Termin**) berbasis penyelesaian Milestone dan penandatanganan BAST per tahap dengan rincian:"))
    body.append(p("   a. **Termin 1 (Uang Muka / DP) : 25%** = Rp [Nominal] (Pemicu: Penandatanganan PKS, penyiapan arsitektur sistem, dan setup environment)."))
    body.append(p("   b. **Termin 2 : 25%** = Rp [Nominal] (Pemicu: Serah terima & uji modul Master SSO & SIMPEG termasuk modul Payroll/Slip Gaji. Ditandai dengan BAST Tahap I)."))
    body.append(p("   c. **Termin 3 : 25%** = Rp [Nominal] (Pemicu: Serah terima & uji modul SIKEU terintegrasi Kas, Pencairan, & Jurnal Penggajian Otomatis. Ditandai dengan BAST Tahap II)."))
    body.append(p("   d. **Termin 4 (Final & Go-Live) : 25%** = Rp [Nominal] (Pemicu: Penyelesaian seluruh integrasi SIAKAD, SPMB, SINAPRA, UAT final, pelatihan staf kampus, dan Go-Live. Ditandai dengan BAST Final)."))
    body.append(p("3. Pembayaran wajib ditransfer oleh PIHAK PERTAMA ke rekening resmi PIHAK KEDUA:"))
    body.append(p("   • **Nama Bank      :** [Bank Mandiri / BCA / BNI / BRI]"))
    body.append(p("   • **Nomor Rekening :** [Nomor Rekening Pengembang]"))
    body.append(p("   • **Atas Nama      :** [Nama Pemilik Rekening / Tim Pengembang]"))
    body.append(p("4. Pembayaran setiap termin wajib dilunasi selambat-lambatnya **7 (Tujuh) hari kalender** setelah invoice dan BAST terkait diterima oleh PIHAK PERTAMA."))
    
    # Pasal 5
    body.append(heading("PASAL 5 — BATASAN PENGERJAAN & MEKANISME CHANGE REQUEST (CR)", level=3))
    body.append(p("1. PIHAK KEDUA hanya berkewajiban mengerjakan fitur-fitur yang tercantum dalam In-Scope pada Lampiran I."))
    body.append(p("2. Apabila selama atau setelah proses pengembangan PIHAK PERTAMA menghendaki penambahan modul baru, pengubahan alur logika bisnis dasar, atau penambahan fitur yang tergolong Out-of-Scope, maka hal tersebut **wajib diajukan melalui Formulir Change Request (CR)**."))
    body.append(p("3. Setiap Change Request akan dihitung secara terpisah oleh PIHAK KEDUA berdasarkan kebutuhan jam kerja (*man-days*) dan akan dituangkan dalam **Addendum Perjanjian** yang memuat biaya tambahan serta penyesuaian jadwal penyerahan."))
    body.append(p("4. PIHAK KEDUA berhak menunda pengerjaan permintaan baru tersebut sampai adanya persetujuan tertulis (*approval*) dari PIHAK PERTAMA mengenai biaya dan jadwal Addendum terkait."))
    
    # Pasal 6
    body.append(heading("PASAL 6 — PENGUJIAN DAN SERAH TERIMA (UAT & BAST)", level=3))
    body.append(p("1. Untuk setiap termin yang selesai, PIHAK KEDUA akan memberikan akses kepada tim PIC PIHAK PERTAMA untuk melakukan *User Acceptance Testing* (UAT)."))
    body.append(p("2. Masa peninjauan dan pengujian UAT oleh PIHAK PERTAMA adalah maksimal **7 (tujuh) hari kerja** sejak akses penyerahan diberikan."))
    body.append(p("3. Apabila dalam kurun waktu 7 hari kerja PIHAK PERTAMA tidak memberikan catatan koreksi tertulis atau tidak menandatangani BAST tanpa alasan teknis yang sah, maka modul pada termin tersebut dianggap telah disetujui (*accepted*) secara sah demi hukum."))
    body.append(p("4. Perbaikan pada tahap UAT dibatasi pada ketidaksesuaian fungsi terhadap *Scope of Work*, bukan permintaan penambahan fitur baru."))
    
    # Pasal 7
    body.append(heading("PASAL 7 — HAK KEKAYAAN INTELEKTUAL & LISENSI", level=3))
    body.append(p("1. Hak Cipta, arsitektur dasar, dan kode sumber (*core framework engine*) Sistem RAGI tetap menjadi hak milik intelektual PIHAK KEDUA."))
    body.append(p("2. PIHAK KEDUA memberikan **Lisensi Penggunaan Penuh dan Tak Terbatas (Full Perpetual License)** kepada PIHAK PERTAMA untuk mengoperasikan, menggunakan, dan menyimpan sistem ini di lingkungan Politeknik Indonusa Surakarta."))
    body.append(p("3. Seluruh data transaksi, data sivitas akademika, database mahasiswa, keuangan, dan data kepegawaian adalah **100% milik sah PIHAK PERTAMA**."))
    body.append(p("4. PIHAK PERTAMA dilarang menduplikasi, menjual kembali (*resell*), mendistribusikan, atau mengalihkan sistem kepada pihak ketiga di luar entitas Politeknik Indonusa Surakarta tanpa izin tertulis dari PIHAK KEDUA."))
    
    # Pasal 8
    body.append(heading("PASAL 8 — KERAHASIAAN INFORMASI (NDA)", level=3))
    body.append(p("1. PARA PIHAK sepakat untuk menjaga kerahasiaan seluruh data, informasi kelembagaan, kredensial akses, dan data pribadi sivitas akademika yang diperoleh selama pelaksanaan kerja sama ini."))
    body.append(p("2. Kewajiban kerahasiaan ini tetap berlaku secara sah meskipun masa berlaku Perjanjian ini telah berakhir."))
    
    # Pasal 9
    body.append(heading("PASAL 9 — GARANSI PEMELIHARAAN (MAINTENANCE & SLA)", level=3))
    body.append(p("1. PIHAK KEDUA memberikan **Garansi Pemeliharaan Gratis selama 3 (Tiga) Bulan** terhitung sejak tanggal penandatanganan BAST Final (Go-Live)."))
    body.append(p("2. Garansi mencakup perbaikan *bug*, kegagalan eksekusi query, perbaikan formula yang tidak sesuai SoW, serta pendampingan teknis admin."))
    body.append(p("3. Garansi tidak mencakup kerusakan akibat modifikasi kode sumber oleh pihak ketiga tanpa persetujuan PIHAK KEDUA, kegagalan infrastruktur hosting/jaringan kampus, atau serangan siber eksternal."))
    body.append(p("4. Layanan pemeliharaan pasca masa garansi 3 bulan berakhir dapat dilanjutkan melalui Kontrak Pemeliharaan Tahunan (*Annual Maintenance Contract / AMC*) terpisah."))
    
    # Pasal 10 & 11
    body.append(heading("PASAL 10 — PENYELESAIAN PERSELISIHAN", level=3))
    body.append(p("1. Segala perselisihan yang timbul sehubungan dengan penafsiran atau pelaksanaan Perjanjian ini akan diselesaikan oleh PARA PIHAK secara musyawarah untuk mufakat."))
    body.append(p("2. Apabila musyawarah tidak mencapai mufakat dalam waktu 30 (tiga puluh) hari kalender, maka PARA PIHAK sepakat menyelesaikan perselisihan tersebut melalui Pengadilan Negeri Surakarta."))
    
    body.append(heading("PASAL 11 — PENUTUP", level=3))
    body.append(p("Perjanjian ini dibuat dalam rangkap 2 (dua) asli, bermeterai cukup Rp 10.000 (Sepuluh Ribu Rupiah) pada masing-masing rangkap, dan mempunyai kekuatan hukum yang sama bagi PARA PIHAK setelah ditandatangani dan dibubuhi cap instansi."))
    body.append(p("", space_after=200))
    
    # Signature Table
    body.append(signature_table())
    body.append(hr())
    
    # LAMPIRAN I
    body.append(heading("LAMPIRAN I: SCOPE OF WORK (SoW) & BATASAN FITUR PER MODUL", level=1))
    body.append(p("Dokumen ini merupakan satu kesatuan yang tidak terpisahkan dari Surat Perjanjian Kerja Sama Nomor: [Nomor PKS].", italic=True, size=20, space_after=140))
    
    sow_headers = ["Modul", "Fitur yang Dikerjakan (In-Scope)", "Batasan Tegas (Out-of-Scope / Tidak Termasuk)"]
    sow_rows = [
        [
            "1. SSO & IAM\n(Master Akses)",
            "• Autentikasi Single Sign-On terpusat\n• Manajemen Pengguna multi-role (RBAC dinamis)\n• Switcher navigasi modul terpadu\n• Audit trail log aktivitas user",
            "• Integrasi Active Directory/LDAP lama non-REST\n• Biometrik wajah/fingerprint login perangkat keras eksternal"
        ],
        [
            "2. SIMPEG & Payroll\n(Kepegawaian)",
            "• Master Pegawai, Dosen, Tendik, NIP/NIDN\n• Presensi GPS/Geolokasi & Shift kerja dinamis\n• Bundling presensi cut-off bulanan (siklus 26-25)\n• Otomasi perhitungan gaji (gaji pokok, tunjangan, insentif hadir, potongan PPh/BPJS)\n• Cetak dokumen resmi Slip Gaji A4 (PDF)\n• Integrasi antrean pengajuan penggajian ke SIKEU",
            "• Integrasi hardware mesin absen sidik jari direct-socket tanpa API (hanya terima input via API / CSV)\n• Perhitungan pajak korporasi kompleks di luar skema PPh 21 standar pegawai"
        ],
        [
            "3. SIKEU\n(Keuangan & Kas)",
            "• Bagan Akun Standar (Chart of Accounts / CoA)\n• Pengelolaan Kas & Rekening Bank Kampus\n• Pengajuan Pencairan Kas Operasional bertingkat (Approval multi-role)\n• Penerimaan otomatis batch payroll dari SIMPEG\n• Pembukuan Jurnal Umum Otomatis seimbang (Debet-Kredit)\n• Laporan realisasi kas & neraca saldo standar",
            "• Sambungan langsung host-to-host (H2H) Core Banking yang membutuhkan lisensi perbankan khusus\n• Rekonsiliasi mutasi bank otomatis tanpa API penyedia perbankan"
        ],
        [
            "4. SIAKAD & OBE\n(Akademik)",
            "• Master Prodi, Dosen Wali, Mahasiswa, Angkatan\n• Rencana Pembelajaran Semester (RPS) berbasis OBE\n• Pengisian KRS Online & Pembimbingan Dosen\n• Input & Validasi Nilai Mutu Dosen (A-E)\n• Cetak KHS & Transkrip Akademik Sementara\n• Monitoring pemetaan CPL & ketercapaian OBE",
            "• Integrasi otomatis feeder PD-DIKTI secara mandiri tanpa kredensial resmi Neo Feeder\n• Modul ujian CBT online berbasis pengawasan video/AI proctoring"
        ],
        [
            "5. SPMB\n(Admisi Mhs Baru)",
            "• Pendaftaran online calon mahasiswa\n• Pilihan Jalur Masuk & Program Studi\n• Upload & Verifikasi berkas administrasi digital\n• Validasi pembayaran formulir pendaftaran\n• Pengumuman kelulusan berkas & Nomor Registrasi",
            "• Dilarang Ujian Tulis / Ujian CBT (sistem admisi murni seleksi berkas/administrasi sesuai regulasi internal kampus)\n• Fitur tes wawancara video call live internal aplikasi"
        ],
        [
            "6. SINAPRA\n(Sarpras & Aset)",
            "• Pendataan Master Aset Kampus & Ruangan\n• Modul pengajuan peminjaman ruangan & aset inventaris\n• Cetak Surat Izin / Bukti Peminjaman A4\n• Status pengembalian & kondisi fisik barang",
            "• Tracking GPS IoT fisik pada inventaris barang bergerak\n• Depresiasi akuntansi aset tetap kompleks multi-metode"
        ]
    ]
    body.append(table(sow_headers, sow_rows, col_widths=[1800, 3700, 3700]))
    
    body.append(heading("ATURAN PENGAMAN PERUBAHAN FITUR (CHANGE REQUEST)", level=3))
    body.append(p("1. Setiap permintaan modifikasi yang tidak terdaftar dalam kolom In-Scope di atas dianggap sebagai **Change Request (CR)**."))
    body.append(p("2. Alur pengajuan CR:"))
    body.append(p("   a. PIHAK PERTAMA mengisi Formulir Permintaan Perubahan resmi."))
    body.append(p("   b. PIHAK KEDUA mengkaji kelayakan teknis dan memberikan estimasi *man-days* (Rp [Nominal]/hari kerja) serta estimasi dampak waktu penyerahan."))
    body.append(p("   c. Jika disetujui PIHAK PERTAMA, pekerjaan CR dituangkan dalam Addendum Kontrak."))
    body.append(hr())
    
    # LAMPIRAN II
    body.append(heading("LAMPIRAN II: JADWAL MILESTONE & TERM OF PAYMENT (ToP)", level=1))
    body.append(p("Ketentuan tahapan pengerjaan dan pembayaran berdasarkan capaian penyelesaian pekerjaan:", italic=True, size=20, space_after=140))
    
    top_headers = ["Tahap", "Uraian Milestone Pekerjaan", "Target Waktu", "Bobot", "Nilai Pembayaran (Rp)", "Dokumen Syarat Cair"]
    top_rows = [
        [
            "Termin 1",
            "Uang Muka (DP) & System Architecture\n• Penandatanganan PKS\n• Konfigurasi Database & Hosting Server Kampus\n• Setup Framework & Master SSO/IAM",
            "Minggu 1 – 2",
            "25%",
            "Rp ........................",
            "Invoice DP + Salinan PKS"
        ],
        [
            "Termin 2",
            "Modul SIMPEG & Payroll Terpadu\n• Migrasi Master Data Pegawai\n• Modul Presensi Shift & Bundling Bulanan\n• Fitur Perhitungan Gaji & Cetak Slip Gaji PDF\n• UAT Parsial SIMPEG",
            "Minggu 3 – 5",
            "25%",
            "Rp ........................",
            "BAST Tahap I + Invoice Termin 2"
        ],
        [
            "Termin 3",
            "Modul SIKEU & Otomasi Akuntansi\n• Setup Bagan Akun (CoA) & Kas Kampus\n• Workflow Pengajuan & Pencairan Operasional\n• Integrasi Penggajian SIMPEG -> SIKEU\n• Jurnal Otomatis Pembayaran Gaji\n• UAT Parsial SIKEU",
            "Minggu 6 – 8",
            "25%",
            "Rp ........................",
            "BAST Tahap II + Invoice Termin 3"
        ],
        [
            "Termin 4",
            "Penyelesaian Sistem, SIAKAD, SPMB, SINAPRA & Go-Live\n• Integrasi Modul Akademik & OBE\n• Integrasi SPMB Jalur Berkas & Aset Sinapra\n• UAT Menyeluruh (End-to-End)\n• Pelatihan (Training) Admin & Operator Kampus\n• Serah Terima Sistem & Resmi Go-Live",
            "Minggu 9 – 12",
            "25%",
            "Rp ........................",
            "BAST Final + Invoice Termin 4"
        ],
        [
            "TOTAL",
            "PENYELESAIAN SELURUH SISTEM RAGI",
            "12 Minggu",
            "100%",
            "Rp ........................",
            "-"
        ]
    ]
    body.append(table(top_headers, top_rows, col_widths=[1000, 3100, 1100, 700, 1600, 1700]))
    body.append(hr())
    
    # LAMPIRAN III
    body.append(heading("LAMPIRAN III: SERVICE LEVEL AGREEMENT (SLA) & GARANSI", level=1))
    body.append(p("1. **Periode Garansi:** 3 (Tiga) Bulan kalender terhitung sejak tanggal BAST Final."))
    body.append(p("2. **Kategori Penanganan Insiden / Bug:**"))
    body.append(p("   • **Kritis (Severity 1):** Sistem mati (*down*), transaksi keuangan/payroll terkunci total, atau kebocoran data. Waktu Respons: Maksimal 2 Jam. Target Solusi: 1x24 Jam."))
    body.append(p("   • **Sedang (Severity 2):** Fitur tertentu tidak berfungsi tetapi proses bisnis utama kampus masih berjalan normal. Waktu Respons: Maksimal 6 Jam. Target Solusi: 2x24 Jam."))
    body.append(p("   • **Minor (Severity 3):** Kesalahan teks, salah penataan tampilan (*glitch styling*), atau pertanyaan operasional. Waktu Respons: 1x24 Jam pada jam kerja operasional (08.00 - 17.00 WIB)."))
    body.append(p("3. **Saluran Dukungan Resmi:**"))
    body.append(p("   • Grup Koordinasi Teknis (WhatsApp Tim Pengembang & Tim IT Kampus)"))
    body.append(p("   • Email Resmi Helpdesk: [email@pengembang.com]"))
    
    # Wrap in Word Document XML
    doc_xml = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    {''.join(body)}
    <w:sectPr>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/>
    </w:sectPr>
  </w:body>
</w:document>"""

    with zipfile.ZipFile(filename, 'w', zipfile.ZIP_DEFLATED) as zf:
        zf.writestr('[Content_Types].xml', content_types)
        zf.writestr('_rels/.rels', rels)
        zf.writestr('word/_rels/document.xml.rels', doc_rels)
        zf.writestr('word/styles.xml', styles)
        zf.writestr('word/document.xml', doc_xml)

    print(f"File {filename} successfully generated!")

if __name__ == '__main__':
    create_docx('/home/wasis/RAGI/Backend/docs/KONTRAK_PKS_RAGI_POLITEKNIK_INDONUSA.docx')

# Dokumentasi API: LuaranSippmController

Controller ini mengelola portofolio luaran publikasi ilmiah, HKI/Buku, serta integrasi narikan data otomatis dari **Scopus (Elsevier)**, **SINTA Kemendikbud**, dan **Crossref (DOI)**.

---

## 1. Tarik Data External (DOI / Scopus / SINTA)
* **Endpoint:** `POST /api/sippm/luaran/fetch-external`
* **Deskripsi:** Menarik pratinjau data publikasi secara real-time dari Scopus, SINTA, atau DOI.

### Request Body:
```json
{
  "source": "doi",
  "identifier": "10.1016/j.solener.2026.01.002"
}
```
> Option `source`: `"doi"`, `"scopus"`, `"sinta"`

### Response (200 OK):
```json
{
  "status": "success",
  "message": "Data publikasi berhasil ditarik dari DOI",
  "data": [
    {
      "source": "scopus_crossref",
      "judul_artikel": "Smart Energy Monitoring & Edge-AI Optimization for Campus Smart Grids",
      "jenis_publikasi": "jurnal_internasional_bereputasi",
      "nama_jurnal_prosiding": "IEEE Transactions on Sustainable Computing",
      "indexing": "scopus_q1",
      "volume_issue_tahun": "Vol. 11, No. 3, 2026",
      "doi": "10.1016/j.solener.2026.01.002",
      "url_artikel": "https://doi.org/10.1016/j.solener.2026.01.002",
      "scopus_eid": "2-s2.0-85199201923",
      "citation_count": 18,
      "publisher": "IEEE / Elsevier B.V.",
      "synced_at": "2026-08-02 00:42:00"
    }
  ]
}
```

---

## 2. Import & Sync Data Publikasi External
* **Endpoint:** `POST /api/sippm/luaran/import-external`
* **Deskripsi:** Menyimpan atau meng-update data publikasi hasil narikan ke database lokal dan mencatat audit trail.

### Request Body:
```json
{
  "pegawai_id": 1,
  "proposal_id": 5,
  "judul_artikel": "Smart Energy Monitoring & Edge-AI Optimization for Campus Smart Grids",
  "jenis_publikasi": "jurnal_internasional_bereputasi",
  "nama_jurnal_prosiding": "IEEE Transactions on Sustainable Computing",
  "indexing": "scopus_q1",
  "volume_issue_tahun": "Vol. 11, No. 3, 2026",
  "doi": "10.1016/j.solener.2026.01.002",
  "scopus_eid": "2-s2.0-85199201923",
  "citation_count": 18,
  "publisher": "Elsevier B.V."
}
```

### Response (201 Created):
```json
{
  "status": "success",
  "message": "Data publikasi berhasil di-import dan disinkronkan ke sistem.",
  "data": {
    "id": 12,
    "pegawai_id": 1,
    "judul_artikel": "Smart Energy Monitoring & Edge-AI Optimization for Campus Smart Grids",
    "indexing": "scopus_q1",
    "is_verified_lppm": true,
    "synced_at": "2026-08-02 00:42:00"
  }
}
```

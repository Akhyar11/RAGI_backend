<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KwitansiPembayaranMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $namaPenerima,
        public string $kodeTransaksi,
        public float $jumlahBayar,
        public string $waktuBayar,
        public string $channel,
        public ?string $nomorTagihan,
        public float $sisaSetelahBayar,
        public string $tautanValidasi,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kwitansi Pembayaran ' . $this->kodeTransaksi . ' - Sistem Keuangan Kampus',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sikeu.kwitansi-pembayaran',
            with: [
                'namaPenerima' => $this->namaPenerima,
                'kodeTransaksi' => $this->kodeTransaksi,
                'jumlahBayar' => $this->jumlahBayar,
                'waktuBayar' => $this->waktuBayar,
                'channel' => $this->channel,
                'nomorTagihan' => $this->nomorTagihan,
                'sisaSetelahBayar' => $this->sisaSetelahBayar,
                'tautanValidasi' => $this->tautanValidasi,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

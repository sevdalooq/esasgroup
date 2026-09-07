<?php

namespace App\Notifications;

/** Aday başvurusu onaylandı / reddedildi */
class ApplicationReviewedNotification extends BaseAppNotification
{
    public function __construct(public bool $approved, public ?string $reason = null)
    {
    }

    public function title(): string
    {
        return $this->approved ? 'Başvurunuz onaylandı' : 'Başvurunuz değerlendirildi';
    }

    public function body(): string
    {
        return $this->approved
            ? 'Esas Grup personel havuzuna kabul edildiniz. Uygun görevler çıktığında sizinle iletişime geçeceğiz.'
            : 'Başvurunuz şu an için olumlu sonuçlanmadı.' . ($this->reason ? ' Not: ' . $this->reason : '');
    }
}

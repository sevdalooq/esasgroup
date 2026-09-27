<?php

namespace App\Notifications;

/** Başlık + metin + yönlendirme verisiyle basit uygulama içi/e-posta bildirimi */
class GenericAppNotification extends BaseAppNotification
{
    public function __construct(
        private readonly string $titleText,
        private readonly string $bodyText,
        private readonly array $data = [],
    ) {}

    public function title(): string
    {
        return $this->titleText;
    }

    public function body(): string
    {
        return $this->bodyText;
    }

    public function payload(): array
    {
        return $this->data;
    }
}

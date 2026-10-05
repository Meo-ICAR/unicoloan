<?php

namespace App\Support;

use App\Models\EmailTemplate;

class EmailTemplateRenderer
{
    public function render(EmailTemplate $template, array $replacements): array
    {
        $subject = $template->subject;
        $body = $template->body;

        foreach ($replacements as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $body = str_replace($placeholder, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}

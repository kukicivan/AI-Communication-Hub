<?php

Namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HeaderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Threading headers
            'message_id' => $this->message_id_header,
            'in_reply_to' => $this->in_reply_to,
            'references' => $this->references ?? [],

            // Utility headers
            'list_unsubscribe' => $this->list_unsubscribe,
            'unsubscribe_link' => $this->when(
                $this->hasUnsubscribeLink(),
                $this->getUnsubscribeLink()
            ),
            'return_path' => $this->return_path,
            'delivered_to' => $this->delivered_to,

            // Security headers
            'received_spf' => $this->received_spf,
            'authentication_results' => $this->authentication_results,
            'dkim_signature' => $this->when(
                !empty($this->dkim_signature),
                substr($this->dkim_signature, 0, 100) . '...'
            ),

            // Security checks
            'is_authenticated' => $this->isAuthenticated(),
            'passed_spf' => $this->hasPassedSpf(),
            'passed_dkim' => $this->hasPassedDkim(),

            // Client info
            'x_mailer' => $this->x_mailer,
            'mailer_client' => $this->getMailerClient(),

            // Priority
            'x_priority' => $this->x_priority,
            'importance' => $this->importance,
            'is_high_priority' => $this->isPriorityHigh(),
            'is_low_priority' => $this->isPriorityLow(),

            // Spam detection
            'x_spam_score' => $this->x_spam_score,
            'spam_score' => $this->getSpamScore(),
            'x_spam_status' => $this->x_spam_status,
            'is_likely_spam' => $this->isLikelySpam(),

            // Custom headers
            'custom_headers' => $this->when(
                !empty($this->custom_headers),
                $this->custom_headers
            ),
        ];
    }
}

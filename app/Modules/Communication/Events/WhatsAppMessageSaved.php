<?php

declare(strict_types=1);

namespace App\Modules\Communication\Events;

use App\Modules\Communication\Models\WhatsAppMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessageSaved
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public WhatsAppMessage $message
    ) {}
}

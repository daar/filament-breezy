<?php

namespace JeffGreco13\FilamentBreezy\Commands;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;

class NotifyCommand extends Command
{
    /** @var string */
    protected $signature = 'breezy:notify';

    /** @var string */
    protected $description = 'Send notifications for any expiring passwords.';

    public function handle()
    {
        if (!config('filament-breezy.enable_password_expiration', false) || !config('filament-breezy.password_expiration_notification_methods', [])) {
            $this->warn("Warning! Enable password expiration and notification.");
        }

        $hasError = false;

        foreach (config('filament-breezy.password_expiration_notification_days', []) as $notificationDay) {

            $users = User::query()
                ->where('updated_at', '<', now()->subDays($notificationDay))
                ->orWhereNull('updated_at')
                ->get();

            foreach ($users as $user) {
                $notificationMethods = config('filament-breezy.password_expiration_notification_methods');

                $updatedDate = Carbon::parse($user->updated_at);
                $expirationDate = $updatedDate->addDays($notificationDay);
                $currentDate = Carbon::now();
                $daysLeft = -1 * $currentDate->diffInDays($expirationDate);

                $notificationText = "You have {$daysLeft} days left before your password expires.";

                if (in_array('database', $notificationMethods)) {
                    Notification::make()
                        ->title($notificationText)
                        ->sendToDatabase($user);
                }
                if (in_array('email', $notificationMethods)) {
                    Mail::raw($notificationText, function ($msg) {
                        $msg->to(config('filament-breezy.password_expiration_notification_email_from'))
                            ->subject(trans('Password expires for :application_name', ['application_name' => config('app.name')]));
                    });
                }
            }
        }

        if ($hasError) {
            return 1;
        }
    }
}

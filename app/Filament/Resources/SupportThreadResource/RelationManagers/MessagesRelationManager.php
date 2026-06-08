<?php

namespace App\Filament\Resources\SupportThreadResource\RelationManagers;

use App\Jobs\SendSupportReplyPush;
use App\Models\SupportMessage;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessagesRelationManager extends RelationManager
{
    private const MAX_MESSAGE_LENGTH = 50000;
    private const MAX_PREVIEW_LENGTH = 255;

    protected static string $relationship = 'messages';

    private function messagePreview(string $body): string
    {
        $preview = preg_replace('/\s+/', ' ', trim($body)) ?: '';

        return Str::substr($preview, 0, self::MAX_PREVIEW_LENGTH);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('sender_type')->label('From')->badge(),
                Tables\Columns\TextColumn::make('body')->label('Message')->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('At')->dateTime(),
            ])
            ->headerActions([
                Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Textarea::make('body')
                            ->label('Message')
                            ->required()
                            ->minLength(1)
                            ->maxLength(self::MAX_MESSAGE_LENGTH),
                    ])
                    ->action(function (array $data) {
                        $thread = $this->getOwnerRecord();
                        $body = trim((string)($data['body'] ?? ''));
                        if ($body === '') return;

                        $preview = $this->messagePreview($body);

                        DB::transaction(function () use ($thread, $body, $preview) {
                            SupportMessage::query()->create([
                                'thread_id' => $thread->id,
                                'org_id' => $thread->org_id,
                                'sender_type' => 'support',
                                'sender_user_id' => null,
                                'body' => $body,
                                'read_at_support' => now(),
                                'read_at_user' => null,
                            ]);

                            $thread->last_message_at = now();
                            $thread->last_message_preview = $preview;
                            $thread->unread_for_user = (int)$thread->unread_for_user + 1;
                            $thread->save();
                        });

                        try {
                            // Keep the queued push payload small; the full reply is stored in support_messages.
                            SendSupportReplyPush::dispatch((int)$thread->org_id, (int)$thread->id, $preview);
                        } catch (\Throwable $e) {
                            Log::error('support_push_queue_failed', [
                                'org_id' => (int)$thread->org_id,
                                'thread_id' => (int)$thread->id,
                                'error' => $e->getMessage(),
                            ]);

                            Notification::make()
                                ->title('Support reply saved')
                                ->body('Push notification was not queued. Please check the logs.')
                                ->warning()
                                ->send();
                        }
                    }),
            ]);
    }
}

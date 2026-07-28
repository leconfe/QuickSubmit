<?php

namespace QuickSubmit\Pages\Concerns;

use App\Actions\Submissions\SubmissionCreateAction;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\Track;
use App\Policies\SubmissionPolicy;
use App\Utils\TinyMceWordCounter;
use Closure;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload as FilamentSpatieMediaLibraryFileUpload;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Stevebauman\Purify\Facades\Purify;

trait HandlesQuickSubmit
{
    public string $show = 'form';

    public ?array $data = [];

    public Submission $submission;

    public function mount(): void
    {
        $this->authorizeQuickSubmit();

        $this->submission = SubmissionCreateAction::run([]);

        $this->form->fill([
            'is_published' => false,
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists(SubmissionPolicy::class, 'submitAs')) {
            return $user->can('submitAs', Submission::class);
        }

        return $user->can('Submission:publish');
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(<<<'HTML'
            <p class="text-sm text-gray-500">This plugin allows you to quickly add complete submissions to the production stage or directly into a proceeding.</p>
        HTML);
    }

    public function submit(): void
    {
        $this->authorizeQuickSubmit();

        $data = $this->form->getState();
        $shouldPublish = (bool) ($data['is_published'] ?? false);

        unset($data['is_published']);

        if ($shouldPublish) {
            Gate::authorize('Submission:publish');

            if (method_exists(SubmissionPolicy::class, 'actAsEditor')) {
                Gate::authorize('actAsEditor', $this->submission);
            }
        }

        try {
            $submission = SubmissionUpdateAction::run(
                $data,
                $this->submission
            );

            $this->form->model($submission)->saveRelationships();

            if ($shouldPublish) {
                $submission = SubmissionUpdateAction::run([
                    'status' => SubmissionStatus::Editing,
                ], $submission);

                $submission->state()->publish();
            } else {
                $submission->state()->fulfill();
            }

            $this->submission = $submission->refresh();

            Notification::make()
                ->success()
                ->title(__('general.saved'))
                ->send();

            $this->show = 'success';
        } catch (\Throwable $th) {
            Notification::make('error')
                ->danger()
                ->title(__('general.error'))
                ->body(__('general.there_was_error_please_contact_administrator'))
                ->send();

            Log::error($th);
        }
    }

    public function submitAnother(): void
    {
        $this->authorizeQuickSubmit();

        $this->submission = SubmissionCreateAction::run([]);

        $this->form->fill([
            'is_published' => false,
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);

        $this->show = 'form';
    }

    public function cancel(): void
    {
        $this->authorizeQuickSubmit();

        $this->submission->delete();

        $this->show = 'cancel';
    }

    protected function isAbstractRequired(mixed $get): bool
    {
        return ! (bool) $this->getSelectedTrack($get)?->getMeta('do_not_require_abstract');
    }

    protected function getAbstractWordLimitRule(mixed $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $wordLimit = (int) ($this->getSelectedTrack($get)?->getMeta('abstract_word_count') ?? 0);

            if (($wordLimit < 1) || blank($value)) {
                return;
            }

            if ($this->countAbstractWords($value) > $wordLimit) {
                $fail(__('general.abstract_word_limit_exceeded', ['count' => $wordLimit]));
            }
        };
    }

    protected function makeCoverUpload(): mixed
    {
        $uploadClass = class_exists(SpatieMediaLibraryFileUpload::class)
            ? SpatieMediaLibraryFileUpload::class
            : FilamentSpatieMediaLibraryFileUpload::class;

        return $uploadClass::make('media.cover')
            ->label(__('general.cover_image'))
            ->collection('cover')
            ->image()
            ->preserveFilenames();
    }

    protected function cleanAbstract(?string $state): ?string
    {
        return Purify::clean($state);
    }

    private function authorizeQuickSubmit(): void
    {
        if (method_exists(SubmissionPolicy::class, 'submitAs')) {
            Gate::authorize('submitAs', Submission::class);

            return;
        }

        Gate::authorize('Submission:publish');
    }

    private function getSelectedTrack(mixed $get): ?Track
    {
        return Track::query()->find($get('track_id'));
    }

    private function countAbstractWords(?string $value): int
    {
        if (class_exists(TinyMceWordCounter::class)) {
            return TinyMceWordCounter::countWords($value);
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (int) preg_match_all("/[\\p{L}\\p{N}]+(?:[’'\\-][\\p{L}\\p{N}]+)*/u", $text);
    }
}

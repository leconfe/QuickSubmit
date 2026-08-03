<?php

namespace QuickSubmit\Pages;

use App\Actions\Submissions\SubmissionCreateAction;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Forms\Components\TinyEditor;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Models\Track;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\GalleyList;
use App\Utils\TinyMceWordCounter;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Stevebauman\Purify\Facades\Purify;

class QuickSubmitPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Quick Submit';

    protected string $view = 'QuickSubmit::quick-submit';

    protected static bool $shouldRegisterNavigation = false;

    public string $show = 'form';

    public ?array $data = [];

    public Submission $submission;

    public static function getRoutePath(Panel $panel): string
    {
        return '/quicksubmit';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('submitAs', Submission::class);
    }

    public function mount(): void
    {
        $this->authorizeQuickSubmit();

        $this->submission = SubmissionCreateAction::run([]);

        $this->form->fill([
            'is_published' => false,
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->submission)
            ->schema([
                Section::make()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('media.cover')
                            ->label(__('general.cover_image'))
                            ->collection('cover')
                            ->image()
                            ->preserveFilenames(),
                        Select::make('track_id')
                            ->label(__('general.track'))
                            ->required()
                            ->options(fn () => Track::active()->pluck('title', 'id'))
                            ->live(),
                        Select::make('topic')
                            ->preload()
                            ->multiple()
                            ->label(__('general.topic'))
                            ->searchable()
                            ->relationship('topics', 'name'),
                        TextInput::make('meta.title')
                            ->label(__('general.title'))
                            ->required(),
                        TagsInput::make('meta.keywords')
                            ->label(__('general.keywords'))
                            ->splitKeys([','])
                            ->placeholder(''),
                        TinyEditor::make('meta.abstract')
                            ->label(__('general.abstract'))
                            ->minHeight(300)
                            ->required(fn (Get $get): bool => $this->isAbstractRequired($get))
                            ->rule(fn (Get $get) => $this->getAbstractWordLimitRule($get))
                            ->dehydrateStateUsing(fn (?string $state) => $this->cleanAbstract($state)),
                        Textarea::make('meta.references')
                            ->label(__('general.references'))
                            ->autosize(),
                        Livewire::make(ContributorList::class, ['submission' => $this->submission])
                            ->key('contributors')
                            ->lazy(),
                        Livewire::make(GalleyList::class, ['submission' => $this->submission])
                            ->key('galleys')
                            ->lazy(),
                        Radio::make('is_published')
                            ->required()
                            ->hiddenLabel()
                            ->inline()
                            ->options([
                                false => __('general.unpublished'),
                                true => __('general.published'),
                            ])
                            ->live(),
                        Grid::make(1)
                            ->visible(fn (Get $get) => $get('is_published'))
                            ->schema([
                                Select::make('proceeding_id')
                                    ->label(__('general.proceeding'))
                                    ->placeholder(__('general.none'))
                                    ->native(false)
                                    ->formatStateUsing(fn () => $this->submission->proceeding_id)
                                    ->options(fn () => [
                                        __('general.future_proceedings') => Proceeding::query()
                                            ->where('published', false)
                                            ->pluck('title', 'id')
                                            ->toArray(),
                                        __('general.back_proceedings') => Proceeding::query()
                                            ->where('published', true)
                                            ->pluck('title', 'id')
                                            ->toArray(),
                                    ]),
                                TextInput::make('meta.isbn')
                                    ->label('ISBN'),
                                TextInput::make('meta.article_pages')
                                    ->label(__('general.pages'))
                                    ->maxWidth('xs')
                                    ->placeholder(__('general.eg_1_10')),
                                DatePicker::make('published_at')
                                    ->maxWidth('xs')
                                    ->label(__('general.date_published'))
                                    ->required(),
                            ]),
                    ]),

            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->authorizeQuickSubmit();

        $data = $this->form->getState();
        $shouldPublish = (bool) ($data['is_published'] ?? false);

        unset($data['is_published']);

        if ($shouldPublish) {
            Gate::authorize('Submission:publish');
            Gate::authorize('actAsEditor', $this->submission);
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

    protected function isAbstractRequired(Get $get): bool
    {
        return ! (bool) $this->getSelectedTrack($get)?->getMeta('do_not_require_abstract');
    }

    protected function getAbstractWordLimitRule(Get $get): Closure
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

    protected function cleanAbstract(?string $state): ?string
    {
        return Purify::clean($state);
    }

    private function authorizeQuickSubmit(): void
    {
        Gate::authorize('submitAs', Submission::class);
    }

    private function getSelectedTrack(Get $get): ?Track
    {
        return Track::query()->find($get('track_id'));
    }

    private function countAbstractWords(?string $value): int
    {
        return TinyMceWordCounter::countWords($value);
    }
}

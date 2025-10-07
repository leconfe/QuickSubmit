<?php

namespace QuickSubmit\Pages;

use App\Actions\Submissions\SubmissionCreateAction;
use App\Actions\Submissions\SubmissionUpdateAction;
use App\Forms\Components\TinyEditor;
use App\Livewire\TestLivewire;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Models\Track;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\GalleyList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

class QuickSubmitPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Quick Submit';

    protected static string $view = 'QuickSubmit::quick-submit';

    protected static bool $shouldRegisterNavigation = false;

    public string $show = 'form';

    public ?array $data = [];

    public Submission $submission;

    public function mount(): void
    {
        $this->submission = SubmissionCreateAction::run([]);


        $this->form->fill([
            'is_published' => false,
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);
    }

    public static function getRoutePath(): string
    {
        return '/quicksubmit';
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString(<<<HTML
            <p class="text-sm text-gray-500">This plugin allows you to quickly add complete submissions to the production stage or directly into a proceeding.</p>
        HTML);
    }

    public function form(Form $form): Form
    {
        return $form
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
                            ->options(fn() => Track::active()->get()->pluck('title', 'id'))
                            ->reactive(),
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
                            ->required(),
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
                            ->visible(fn(Get $get) => $get('is_published'))
                            ->schema([
                                Select::make('proceeding_id')
                                    ->label(__('general.proceeding'))
                                    ->placeholder(__('general.none'))
                                    ->native(false)
                                    ->formatStateUsing(fn() => $submission->proceeding_id ?? null)
                                    // ->native(false)
                                    // ->searchable()
                                    ->options(
                                        [
                                            __('general.future_proceedings') => Proceeding::query()
                                                ->where('published', false)
                                                ->pluck('title', 'id')
                                                ->toArray(),
                                            __('general.back_proceedings') => Proceeding::query()
                                                ->where('published', true)
                                                ->pluck('title', 'id')
                                                ->toArray(),
                                        ]
                                    ),
                                TextInput::make('meta.isbn')
                                    ->label("ISBN"),
                                TextInput::make('meta.article_pages')
                                    ->label(__('general.pages'))
                                    ->maxWidth('xs')
                                    ->placeholder(__('general.eg_1_10')),
                                DatePicker::make('published_at')
                                    ->maxWidth('xs')
                                    ->label(__('general.date_published'))
                                    ->required(),
                            ])
                    ])

            ])
            ->statePath('data');
    }

    public function submit()
    {
        $data = $this->form->getState();

        if($data['is_published']){
            $data['stage'] = SubmissionStage::Proceeding;
            $data['status'] = SubmissionStatus::Published;
        } else {
            $data['stage'] = SubmissionStage::CallforAbstract;
            $data['status'] = SubmissionStatus::Queued;
        }

        try {
            $submission = SubmissionUpdateAction::run(
                $data,
                $this->submission
            );

            $this->form->model($submission)->saveRelationships();

            Notification::make('success')
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

    public function submitAnother()
    {
        $this->submission = SubmissionCreateAction::run([]);

        $this->form->fill([
            'is_published' => false,
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);

        $this->show = 'form';
    }

    public function cancel()
    {
        $this->submission->delete();

        $this->show = 'cancel';
    }
}

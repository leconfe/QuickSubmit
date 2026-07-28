<?php

namespace QuickSubmit\Pages;

use App\Forms\Components\TinyEditor;
use App\Models\Proceeding;
use App\Models\Track;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ContributorList;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\GalleyList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page as FilamentPage;
use QuickSubmit\Pages\Concerns\HandlesQuickSubmit;

class QuickSubmitPageV3 extends FilamentPage implements HasForms
{
    use HandlesQuickSubmit;
    use InteractsWithForms;

    protected static ?string $title = 'Quick Submit';

    protected static string $view = 'QuickSubmit::quick-submit';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'quicksubmit';

    public function form(Form $form): Form
    {
        return $form
            ->model($this->submission)
            ->schema([
                Section::make()
                    ->schema([
                        $this->makeCoverUpload(),
                        Select::make('track_id')
                            ->label(__('general.track'))
                            ->required()
                            ->options(fn () => Track::active()->pluck('title', 'id'))
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
}

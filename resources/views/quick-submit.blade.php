<x-filament-panels::page>
    @if($show == 'form')
        <form wire:submit='submit' class="space-y-4">
            {{ $this->form }}
            <div class="flex items-center gap-2">
                <x-filament::button type="submit" icon="iconpark-save-o">
                    {{ __('general.submit') }}
                </x-filament::button>
                <x-filament::button wire:click='cancel' color="danger">
                    {{ __('general.cancel') }}
                </x-filament::button>
            </div>
        </form>
    @elseif($show == 'success')
        <x-filament::section>
            <x-slot name="heading">
                Quick submit success
            </x-slot>
            
            <div class="flex items-center gap-2">
                <x-filament::button color="success" :href="\App\Panel\ScheduledConference\Resources\SubmissionResource::getUrl('view', ['record' => $this->submission])" tag="a">
                    View Submission
                </x-filament::button>
                <x-filament::button wire:click="submitAnother">
                    Submit Another
                </x-filament::button>
            </div>
        </x-filament::section>
    @elseif($show == 'cancel')
        <x-filament::section>
            <x-slot name="heading">
                Quick submit cancelled
            </x-slot>
            
            <div class="flex items-center gap-2">
                <x-filament::button wire:click="submitAnother">
                    Submit Another
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
<?php

namespace Workbench\App\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\ContactResource\Pages\ListContacts;
use Workbench\App\Models\Contact;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('job_title')->label('Job title'),
                TextColumn::make('company')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('5s');
    }

    public static function getPages(): array
    {
        return ['index' => ListContacts::route('/')];
    }
}

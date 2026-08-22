<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\ParticipantCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ParticipantImportController extends Controller
{
    public function __construct(private readonly ParticipantCsvImportService $importer) {}

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            $result = $this->importer->import($tournament, $request->file('csv_file'));

            return back()
                ->with('success', __('ui.csv_imported', [
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                ]))
                ->with('import_errors', array_slice($result['errors'], 0, 20));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors($exception->getMessage());
        }
    }

    public function template(): Response
    {
        $content = "\xEF\xBB\xBFTeam Name,Team ID,School,Coach,Member 1,Member 2,Member 3,Member 4,Seed\n";
        $content .= 'Robo Tigers,RBT,EasyKids Academy,Coach A,Student One,Student Two,,,1'."\n";

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="easykids-participants-template.csv"',
        ]);
    }
}

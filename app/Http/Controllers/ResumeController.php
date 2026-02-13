<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ResumeParserService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ResumeController extends Controller
{
    protected $resumeParser;

    public function __construct(ResumeParserService $resumeParser)
    {
        $this->resumeParser = $resumeParser;
    }

    /**
     * Show the resume parser form
     */
    public function index()
    {
        return view('resume.index');
    }

    /**
     * Handle PDF upload and extract text
     */
    public function upload(Request $request)
    {
        try {
            // Validate the uploaded file
            $request->validate([
                'resume' => 'required|file|mimes:pdf|max:10240' // 10MB max
            ], [
                'resume.required' => 'Please select a PDF file to upload.',
                'resume.mimes' => 'Only PDF files are allowed.',
                'resume.max' => 'File size must be less than 10MB.'
            ]);

            // Store the uploaded file temporarily
            $file = $request->file('resume');
            $path = $file->store('temp/resumes');
            $fullPath = Storage::path($path);

            Log::info('Processing PDF: ' . $file->getClientOriginalName() . ' (Size: ' . round($file->getSize()/1024, 2) . 'KB)');

            // Extract text from PDF with enhanced methods
            $extractedText = $this->resumeParser->extractTextFromPdf($fullPath);
            
            if (empty($extractedText)) {
                return back()->with('error', 'Could not extract text from the PDF. Please ensure the PDF contains readable text or try a different PDF format.');
            }

            Log::info('Successfully extracted ' . strlen($extractedText) . ' characters from PDF');

            // Parse the extracted text to structured data
            $parsedData = $this->resumeParser->parseResumeText($extractedText);

            // Clean up temporary file
            Storage::delete($path);

            // Add debug information
            $debugInfo = [
                'total_characters' => strlen($extractedText),
                'total_lines' => count(explode("\n", $extractedText)),
                'words_found' => str_word_count($extractedText),
                'extraction_preview' => substr($extractedText, 0, 200) . (strlen($extractedText) > 200 ? '...' : ''),
            ];

            // Return the form with extracted data and debug info
            return view('resume.form', [
                'data' => $parsedData,
                'extractedText' => $extractedText,
                'fileName' => $file->getClientOriginalName(),
                'debugInfo' => $debugInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Resume upload error: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while processing your resume: ' . $e->getMessage());
        }
    }

    /**
     * Save the resume data to database (optional)
     */
    public function save(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'summary' => 'nullable|string|max:2000',
            'skills' => 'nullable|string|max:1000',
            'experience' => 'nullable|string|max:3000',
            'education' => 'nullable|string|max:1000',
        ]);

        // Here you can save to database if needed
        // For now, we'll just return a success message
        
        return back()->with('success', 'Resume data has been processed successfully!')->with('saved_data', $request->all());
    }
}
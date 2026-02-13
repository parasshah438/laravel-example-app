<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;

class ResumeParserService
{
    use ResumeFormatParsers; // Include format-specific parsing methods
    
    protected $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extract text from PDF file using multiple methods
     */
    public function extractTextFromPdf($filePath)
    {
        try {
            $pdf = $this->parser->parseFile($filePath);
            Log::info('PDF parsed successfully, attempting text extraction...');
            
            // Method 1: Extract text from entire document (primary method)
            $fullText = '';
            try {
                $fullText = $pdf->getText();
                Log::info('Full document text extraction: ' . strlen($fullText) . ' characters');
            } catch (\Exception $e) {
                Log::warning('Full document text extraction failed: ' . $e->getMessage());
            }
            
            // Method 2: Extract text from each page individually
            $pageTexts = [];
            try {
                $pages = $pdf->getPages();
                Log::info('Found ' . count($pages) . ' pages in PDF');
                
                foreach ($pages as $pageNumber => $page) {
                    try {
                        // Get text from page
                        $pageText = $page->getText();
                        if (!empty($pageText)) {
                            $pageTexts[] = $pageText;
                            Log::info('Page ' . ($pageNumber + 1) . ' extracted: ' . strlen($pageText) . ' characters');
                        }
                        
                        // Try additional content extraction (but catch errors gracefully)
                        try {
                            $additionalText = $this->extractAdditionalContent($page);
                            if (!empty($additionalText)) {
                                $pageTexts[] = $additionalText;
                                Log::info('Additional content from page ' . ($pageNumber + 1) . ': ' . strlen($additionalText) . ' characters');
                            }
                        } catch (\Exception $additionalError) {
                            Log::warning('Additional content extraction failed for page ' . ($pageNumber + 1) . ': ' . $additionalError->getMessage());
                        }
                        
                    } catch (\Exception $pageError) {
                        Log::warning('Error extracting text from page ' . ($pageNumber + 1) . ': ' . $pageError->getMessage());
                        continue;
                    }
                }
            } catch (\Exception $pageError) {
                Log::warning('Error accessing pages: ' . $pageError->getMessage());
            }
            
            // Combine extraction methods
            $combinedText = $fullText;
            
            // Add page-by-page extracted text if it contains more content
            $pagesCombined = implode("\n\n", $pageTexts);
            if (strlen($pagesCombined) > strlen($fullText)) {
                $combinedText = $pagesCombined;
                Log::info('Using page-by-page extraction as it yielded more content');
            }
            
            // Method 3: Try to extract text from document metadata
            try {
                $metadataText = $this->extractMetadataText($pdf);
                if (!empty($metadataText)) {
                    $combinedText .= "\n\n" . $metadataText;
                    Log::info('Metadata text added: ' . strlen($metadataText) . ' characters');
                }
            } catch (\Exception $metaError) {
                Log::warning('Metadata extraction failed: ' . $metaError->getMessage());
            }
            
            // Method 4: Extract text from form fields if present
            try {
                $formText = $this->extractFormFieldText($pdf);
                if (!empty($formText)) {
                    $combinedText .= "\n\n" . $formText;
                    Log::info('Form field text added: ' . strlen($formText) . ' characters');
                }
            } catch (\Exception $formError) {
                Log::warning('Form field extraction failed: ' . $formError->getMessage());
            }
            
            if (empty($combinedText) || strlen(trim($combinedText)) < 10) {
                throw new \Exception('No readable text found in PDF. The file might be image-based, corrupted, or password-protected.');
            }
            
            Log::info('PDF text extraction successful. Total extracted: ' . strlen($combinedText) . ' characters.');
            
            // Clean up and return the extracted text
            return $this->cleanText($combinedText);
            
        } catch (\Exception $e) {
            Log::error('PDF text extraction error: ' . $e->getMessage());
            throw new \Exception('Failed to extract text from PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract additional content from page objects
     */
    private function extractAdditionalContent($page)
    {
        try {
            $content = '';
            $details = $page->getDetails();
            
            // Try to extract text from different content streams
            if (isset($details['Resources']) && is_array($details['Resources'])) {
                $resources = $details['Resources'];
                
                // Look for XObject content
                if (isset($resources['XObject']) && is_array($resources['XObject'])) {
                    foreach ($resources['XObject'] as $xobj) {
                        try {
                            if (is_object($xobj) && method_exists($xobj, 'getText')) {
                                $xobjText = $xobj->getText();
                                if (!empty($xobjText)) {
                                    $content .= "\n" . $xobjText;
                                }
                            }
                        } catch (\Exception $e) {
                            // Continue if this XObject fails
                            continue;
                        }
                    }
                }
                
                // Look for Font content (sometimes contains text)
                if (isset($resources['Font']) && is_array($resources['Font'])) {
                    // Font resources might contain embedded text
                    foreach ($resources['Font'] as $font) {
                        try {
                            if (is_object($font) && method_exists($font, 'getDetails')) {
                                $fontDetails = $font->getDetails();
                                if (isset($fontDetails['ToUnicode']) && is_object($fontDetails['ToUnicode']) && method_exists($fontDetails['ToUnicode'], 'getText')) {
                                    $unicodeText = $fontDetails['ToUnicode']->getText();
                                    // Process unicode mappings if they contain readable text
                                    $readableText = $this->extractReadableFromUnicode($unicodeText);
                                    if (!empty($readableText)) {
                                        $content .= "\n" . $readableText;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
            }
            
            return trim($content);
            
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Extract text from PDF metadata
     */
    private function extractMetadataText($pdf)
    {
        try {
            $content = '';
            $details = $pdf->getDetails();
            
            // Extract relevant metadata that might contain text content
            $metadataFields = ['Title', 'Subject', 'Author', 'Keywords', 'Creator', 'Producer'];
            
            foreach ($metadataFields as $field) {
                if (isset($details[$field]) && !empty($details[$field])) {
                    $value = '';
                    
                    // Handle different data types
                    if (is_string($details[$field])) {
                        $value = $details[$field];
                    } elseif (is_object($details[$field]) && method_exists($details[$field], '__toString')) {
                        $value = (string)$details[$field];
                    } elseif (is_object($details[$field]) && method_exists($details[$field], 'getContent')) {
                        $value = $details[$field]->getContent();
                    } elseif (is_scalar($details[$field])) {
                        $value = (string)$details[$field];
                    }
                    
                    if (strlen($value) > 2) { // Only include meaningful metadata
                        $content .= $field . ': ' . $value . "\n";
                    }
                }
            }
            
            return trim($content);
            
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Extract text from form fields
     */
    private function extractFormFieldText($pdf)
    {
        try {
            $content = '';
            $details = $pdf->getDetails();
            
            // Look for AcroForm (form fields)
            if (isset($details['AcroForm']) && is_array($details['AcroForm']) && isset($details['AcroForm']['Fields']) && is_array($details['AcroForm']['Fields'])) {
                foreach ($details['AcroForm']['Fields'] as $field) {
                    if (is_array($field) && isset($field['V']) && !empty($field['V'])) {
                        // Field value
                        $value = '';
                        
                        if (is_string($field['V'])) {
                            $value = $field['V'];
                        } elseif (is_object($field['V']) && method_exists($field['V'], '__toString')) {
                            $value = (string)$field['V'];
                        } elseif (is_scalar($field['V'])) {
                            $value = (string)$field['V'];
                        }
                        
                        if (strlen($value) > 1) {
                            $fieldName = 'Field';
                            if (isset($field['T'])) {
                                if (is_string($field['T'])) {
                                    $fieldName = $field['T'];
                                } elseif (is_object($field['T']) && method_exists($field['T'], '__toString')) {
                                    $fieldName = (string)$field['T'];
                                }
                            }
                            $content .= $fieldName . ': ' . $value . "\n";
                        }
                    }
                }
            }
            
            return trim($content);
            
        } catch (\Exception $e) {
            return '';
        }
    }
    
    /**
     * Extract readable text from unicode mappings
     */
    private function extractReadableFromUnicode($unicodeText)
    {
        try {
            // This is a simplified approach - in practice, you'd need more sophisticated unicode processing
            $readable = '';
            
            // Look for common text patterns in unicode mappings
            if (preg_match_all('/\((.*?)\)/', $unicodeText, $matches)) {
                foreach ($matches[1] as $match) {
                    if (preg_match('/^[a-zA-Z0-9\s\.\@\-\+]+$/', $match) && strlen($match) > 3) {
                        $readable .= $match . ' ';
                    }
                }
            }
            
            return trim($readable);
            
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Parse resume text and extract structured information using multiple strategies
     */
    public function parseResumeText($text)
    {
        // Try multiple parsing strategies and combine the best results
        $strategies = [
            'standard' => $this->parseStandardFormat($text),
            'structured' => $this->parseStructuredFormat($text), 
            'tabular' => $this->parseTabularFormat($text),
            'academic' => $this->parseAcademicFormat($text),
            'international' => $this->parseInternationalFormat($text),
            'modern' => $this->parseModernFormat($text)
        ];
        
        // Combine results using confidence scoring
        $combinedData = $this->combineParsingResults($strategies);
        
        Log::info('Resume parsing completed using multiple strategies', [
            'strategies_used' => count($strategies),
            'final_confidence' => $combinedData['_confidence'] ?? 0
        ]);
        
        // Remove confidence metadata before returning
        unset($combinedData['_confidence']);
        
        return $combinedData;
    }
    
    /**
     * Standard resume format parsing
     */
    private function parseStandardFormat($text)
    {
        return [
            'name' => $this->extractName($text),
            'email' => $this->extractEmail($text),
            'phone' => $this->extractPhone($text),
            'address' => $this->extractAddress($text),
            'summary' => $this->extractSummary($text),
            'skills' => $this->extractSkills($text),
            'experience' => $this->extractExperience($text),
            'education' => $this->extractEducation($text),
        ];
    }
    
    /**
     * Structured format with clear sections (headers, bullet points)
     */
    private function parseStructuredFormat($text)
    {
        return [
            'name' => $this->extractNameStructured($text),
            'email' => $this->extractEmailUniversal($text),
            'phone' => $this->extractPhoneUniversal($text),
            'address' => $this->extractAddressStructured($text),
            'summary' => $this->extractSummaryStructured($text),
            'skills' => $this->extractSkillsStructured($text),
            'experience' => $this->extractExperienceStructured($text),
            'education' => $this->extractEducationStructured($text),
        ];
    }
    
    /**
     * Tabular format parsing (tables, columns)
     */
    private function parseTabularFormat($text)
    {
        return [
            'name' => $this->extractNameFromTables($text),
            'email' => $this->extractEmailUniversal($text),
            'phone' => $this->extractPhoneUniversal($text),
            'address' => $this->extractAddressFromTables($text),
            'summary' => $this->extractSummaryFromTables($text),
            'skills' => $this->extractSkillsFromTables($text),
            'experience' => $this->extractExperienceFromTables($text),
            'education' => $this->extractEducationFromTables($text),
        ];
    }
    
    /**
     * Academic CV format parsing
     */
    private function parseAcademicFormat($text)
    {
        return [
            'name' => $this->extractNameAcademic($text),
            'email' => $this->extractEmailUniversal($text),
            'phone' => $this->extractPhoneUniversal($text),
            'address' => $this->extractAddressAcademic($text),
            'summary' => $this->extractSummaryAcademic($text),
            'skills' => $this->extractSkillsAcademic($text),
            'experience' => $this->extractExperienceAcademic($text),
            'education' => $this->extractEducationAcademic($text),
        ];
    }
    
    /**
     * International format parsing (different cultures/countries)
     */
    private function parseInternationalFormat($text)
    {
        return [
            'name' => $this->extractNameInternational($text),
            'email' => $this->extractEmailUniversal($text),
            'phone' => $this->extractPhoneInternational($text),
            'address' => $this->extractAddressInternational($text),
            'summary' => $this->extractSummaryInternational($text),
            'skills' => $this->extractSkillsInternational($text),
            'experience' => $this->extractExperienceInternational($text),
            'education' => $this->extractEducationInternational($text),
        ];
    }
    
    /**
     * Modern resume format parsing (creative, minimal, etc.)
     */
    private function parseModernFormat($text)
    {
        return [
            'name' => $this->extractNameModern($text),
            'email' => $this->extractEmailUniversal($text),
            'phone' => $this->extractPhoneUniversal($text),
            'address' => $this->extractAddressModern($text),
            'summary' => $this->extractSummaryModern($text),
            'skills' => $this->extractSkillsModern($text),
            'experience' => $this->extractExperienceModern($text),
            'education' => $this->extractEducationModern($text),
        ];
    }
    
    /**
     * Combine results from multiple parsing strategies using confidence scoring
     */
    private function combineParsingResults($strategies)
    {
        $combined = [];
        $confidence = [];
        
        // Fields to extract
        $fields = ['name', 'email', 'phone', 'address', 'summary', 'skills', 'experience', 'education'];
        
        foreach ($fields as $field) {
            $fieldResults = [];
            
            // Collect all non-empty results for this field
            foreach ($strategies as $strategy => $data) {
                if (!empty($data[$field])) {
                    $fieldResults[] = [
                        'value' => $data[$field],
                        'strategy' => $strategy,
                        'confidence' => $this->calculateFieldConfidence($field, $data[$field], $strategy)
                    ];
                }
            }
            
            // Sort by confidence and pick the best
            if (!empty($fieldResults)) {
                usort($fieldResults, function($a, $b) {
                    return $b['confidence'] - $a['confidence'];
                });
                
                $combined[$field] = $fieldResults[0]['value'];
                $confidence[$field] = $fieldResults[0]['confidence'];
            } else {
                $combined[$field] = '';
                $confidence[$field] = 0;
            }
        }
        
        // Calculate overall confidence
        $combined['_confidence'] = array_sum($confidence) / count($fields);
        
        return $combined;
    }
    
    /**
     * Calculate confidence score for extracted field data
     */
    private function calculateFieldConfidence($field, $value, $strategy)
    {
        $confidence = 50; // Base confidence
        
        // Field-specific confidence boosts
        switch ($field) {
            case 'name':
                if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+$/', $value)) $confidence += 30;
                if (str_word_count($value) >= 2 && str_word_count($value) <= 3) $confidence += 20;
                break;
                
            case 'email':
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) $confidence += 40;
                if (preg_match('/gmail|yahoo|hotmail|outlook/', $value)) $confidence += 10;
                break;
                
            case 'phone':
                if (preg_match('/^\+/', $value)) $confidence += 20;
                $digits = preg_replace('/\D/', '', $value);
                if (strlen($digits) >= 10 && strlen($digits) <= 15) $confidence += 20;
                break;
                
            case 'address':
                if (preg_match('/\d+/', $value)) $confidence += 15;
                if (preg_match('/,/', $value)) $confidence += 10;
                if (strlen($value) > 20) $confidence += 10;
                break;
        }
        
        // Strategy-specific confidence adjustments
        switch ($strategy) {
            case 'structured':
                $confidence += 10; // Usually more reliable
                break;
            case 'academic':
                if (in_array($field, ['education', 'experience'])) $confidence += 15;
                break;
            case 'international':
                if ($field === 'phone') $confidence += 10;
                break;
        }
        
        // Length and quality checks
        if (!empty($value)) {
            if (strlen($value) > 3) $confidence += 5;
            if (strlen($value) > 10) $confidence += 5;
        } else {
            $confidence = 0;
        }
        
        return min(100, max(0, $confidence));
    }

    /**
     * Clean and normalize extracted text while preserving structure
     */
    private function cleanText($text)
    {
        // First, normalize different types of line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Remove null characters and other control characters except newlines and tabs
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize multiple spaces but preserve single line breaks
        $text = preg_replace('/[ \t]+/', ' ', $text);
        
        // Normalize multiple consecutive line breaks (max 2)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Remove leading/trailing spaces from each line while preserving line structure
        $lines = explode("\n", $text);
        $cleanLines = [];
        
        foreach ($lines as $line) {
            $cleanLine = trim($line);
            // Keep non-empty lines and preserve intentional line breaks
            if (!empty($cleanLine) || (!empty($cleanLines) && !empty($cleanLines[count($cleanLines) - 1]))) {
                $cleanLines[] = $cleanLine;
            }
        }
        
        // Join lines back together
        $text = implode("\n", $cleanLines);
        
        // Final cleanup - remove any remaining excessive whitespace
        $text = trim($text);
        
        // Decode HTML entities that might be present
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        return $text;
    }

    /**
     * Extract name from resume text with improved detection
     */
    private function extractName($text)
    {
        $lines = explode("\n", $text);
        $potentialNames = [];
        
        // Method 1: Look at the very first non-empty line (most common for resumes)
        for ($i = 0; $i < min(3, count($lines)); $i++) {
            $line = trim($lines[$i]);
            
            // Skip empty lines
            if (empty($line) || strlen($line) < 3) {
                continue;
            }
            
            // Skip lines that are clearly not names
            if ($this->isNotAName($line)) {
                continue;
            }
            
            // Give high score to early lines that look like names
            $nameScore = $this->calculateNameScore($line);
            if ($nameScore > 0) {
                $potentialNames[] = [
                    'text' => $line,
                    'score' => $nameScore + (20 - ($i * 5)), // Higher score for earlier lines
                    'position' => $i
                ];
            }
        }
        
        // Method 2: Look for standalone name lines (lines that are just names)
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line) || $this->isNotAName($line)) continue;
            
            // Check if it's a standalone name (no colons, numbers, symbols)
            if (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]*\.?)*\s+[A-Z][a-z]+$/', $line)) {
                $potentialNames[] = [
                    'text' => $line,
                    'score' => 25 + ($index < 5 ? (5 - $index) : 0), // Bonus for early standalone names
                    'position' => $index
                ];
            }
        }
        
        // Method 3: Look for name patterns throughout the document
        $namePatterns = ['/Name[:\s]*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i'];
        
        foreach ($namePatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleanMatch = trim($match);
                    if (!empty($cleanMatch) && !$this->isNotAName($cleanMatch)) {
                        $potentialNames[] = [
                            'text' => $cleanMatch,
                            'score' => 15,
                            'position' => 999
                        ];
                    }
                }
            }
        }
        
        // Method 4: Look for metadata names
        if (preg_match('/Creator[:\s]+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i', $text, $matches)) {
            $authorName = trim($matches[1]);
            if (!$this->isNotAName($authorName)) {
                $potentialNames[] = [
                    'text' => $authorName,
                    'score' => 12,
                    'position' => 1000
                ];
            }
        }
        
        // Sort by score (highest first), then by position (earliest first)
        usort($potentialNames, function($a, $b) {
            if ($a['score'] === $b['score']) {
                return $a['position'] - $b['position'];
            }
            return $b['score'] - $a['score'];
        });
        
        return !empty($potentialNames) ? $potentialNames[0]['text'] : '';
    }
    
    /**
     * Check if a line is clearly not a name
     */
    private function isNotAName($line)
    {
        // Skip if contains email
        if (preg_match('/@/', $line)) return true;
        
        // Skip if contains phone numbers (3+ consecutive digits)
        if (preg_match('/\d{3,}/', $line)) return true;
        
        // Skip if contains URLs
        if (preg_match('/https?:\/\/|www\./', $line)) return true;
        
        // Skip if contains common resume headers/keywords
        $headers = ['resume', 'curriculum vitae', 'cv', 'profile', 'summary', 'objective', 
                   'experience', 'education', 'skills', 'contact', 'address', 'qualification',
                   'email', 'mobile', 'phone', 'linkedin', 'github', 'portfolio',
                   'course', 'institute', 'university', 'college', 'school', 'year',
                   'cgpa', 'percentage', 'marks', 'grade', 'certification', 'training',
                   'project', 'intern', 'internship', 'work', 'employment', 'position',
                   'responsibility', 'achievement', 'award', 'honor', 'activities'];
        
        foreach ($headers as $header) {
            if (stripos($line, $header) !== false) return true;
        }
        
        // Skip if contains academic/professional patterns
        if (preg_match('/\b(B\.?\s*Tech|M\.?\s*Tech|B\.?\s*E|M\.?\s*E|Ph\.?\s*D|MBA|BBA|BSc|MSc|BA|MA|CBSE|ICSE)\b/i', $line)) return true;
        
        // Skip if contains table-like structures
        if (preg_match('/\b(Name\s+of|Year|Institute|University|CGPA|Percentage)\b/i', $line)) return true;
        
        // Skip if contains special characters that names typically don't have
        if (preg_match('/[#$%&*+=<>{}[\]|\\\\\/]/', $line)) return true;
        
        // Skip if contains colons (typically headers)
        if (preg_match('/:/', $line)) return true;
        
        // Skip if too many numbers relative to letters
        $digitCount = preg_match_all('/\d/', $line, $matches);
        $letterCount = preg_match_all('/[a-zA-Z]/', $line, $matches);
        if ($digitCount > 2 && $letterCount > 0 && ($digitCount / $letterCount) > 0.3) return true;
        
        // Skip if all lowercase (names usually have capitals)
        if ($line === strtolower($line) && strlen($line) > 5) return true;
        
        // Skip if all uppercase and too long (likely a title/header)
        if ($line === strtoupper($line) && strlen($line) > 20) return true;
        
        // Skip if contains common non-name patterns
        if (preg_match('/\b(of|the|and|or|in|at|to|for|with|by|from|on|as|is|are|was|were)\s+(Course|Year|Institute|University|College|School)\b/i', $line)) return true;
        
        return false;
    }
    
    /**
     * Calculate a score for how likely a string is to be a name
     */
    private function calculateNameScore($line)
    {
        $score = 0;
        $words = explode(' ', trim($line));
        
        // Base score for having the right number of words
        $wordCount = count($words);
        if ($wordCount >= 2 && $wordCount <= 4) {
            $score += 15; // Increased for proper name length
        } elseif ($wordCount === 1) {
            $score += 8; // Single names are possible but less likely
        } else {
            return 0; // Too many or too few words
        }
        
        // Check each word for name-like properties
        $validWordCount = 0;
        foreach ($words as $word) {
            $word = trim($word, '.,');
            
            // Must contain only letters, hyphens, apostrophes, and periods
            if (!preg_match('/^[A-Za-z\-\'.]+$/', $word)) {
                return 0;
            }
            
            // Must be reasonable length
            if (strlen($word) < 2 || strlen($word) > 20) {
                return 0;
            }
            
            $validWordCount++;
            
            // Bonus for proper capitalization (First letter capital, rest lowercase)
            if (preg_match('/^[A-Z][a-z]+$/', $word)) {
                $score += 8;
            }
            
            // Bonus for middle initials
            if (preg_match('/^[A-Z]\.?$/', $word)) {
                $score += 5;
            }
            
            // Bonus for common name patterns
            if (preg_match('/^[A-Z][a-z]{2,}$/', $word)) {
                $score += 3;
            }
            
            // Penalty for common non-name words
            $commonWords = ['of', 'the', 'and', 'in', 'at', 'to', 'for', 'course', 'year', 
                           'institute', 'university', 'college', 'school', 'education'];
            if (in_array(strtolower($word), $commonWords)) {
                return 0; // Immediately disqualify
            }
        }
        
        // Bonus for having all valid words
        if ($validWordCount === $wordCount) {
            $score += 5;
        }
        
        // Total length bonus
        $totalLength = strlen($line);
        if ($totalLength >= 5 && $totalLength <= 40) {
            $score += 3;
        }
        
        // Bonus for proper name format (First Last or First Middle Last)
        if ($wordCount === 2 && preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+$/', $line)) {
            $score += 10; // Strong bonus for "First Last" format
        }
        
        if ($wordCount === 3 && preg_match('/^[A-Z][a-z]+\s+[A-Z]\.?\s+[A-Z][a-z]+$/', $line)) {
            $score += 8; // Bonus for "First M. Last" format
        }
        
        // Penalty for mixed case that doesn't look like names
        if (!preg_match('/^[A-Z][a-z]*(\s+[A-Z]\.?\s*)*[A-Z][a-z]*$/', $line)) {
            $score -= 5;
        }
        
        return max(0, $score);
    }

    /**
     * Universal email extraction with context awareness
     */
    private function extractEmailUniversal($text)
    {
        $lines = explode("\n", $text);
        $emailCandidates = [];
        
        // Look for emails in the first portion of the resume (header area)
        for ($i = 0; $i < min(15, count($lines)); $i++) {
            $line = trim($lines[$i]);
            
            // Skip section headers that might contain emails
            if (preg_match('/^(education|experience|skills|projects|certifications?|references?|background)[:\s]*$/i', $line)) {
                continue;
            }
            
            // Extract email from line
            if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $line, $matches)) {
                $email = trim($matches[1]);
                
                // Calculate priority score (higher for earlier lines, lower if mixed with section text)
                $priority = 100 - ($i * 5);
                
                // Reduce priority if line contains section keywords
                if (preg_match('/\b(education|experience|background|qualification|academic)\b/i', $line)) {
                    $priority -= 30;
                }
                
                // Increase priority if it's a clean standalone email line
                if (preg_match('/^[\s]*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})[\s]*$/', $line)) {
                    $priority += 20;
                }
                
                // Increase priority if it has email labels
                if (preg_match('/\b(email|e-mail|mail|contact)\b[:\s]*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $line, $labelMatches)) {
                    $email = trim($labelMatches[2]);
                    $priority += 10;
                }
                
                $emailCandidates[] = [
                    'email' => $email,
                    'priority' => $priority,
                    'line' => $i
                ];
            }
        }
        
        // Return the highest priority email
        if (!empty($emailCandidates)) {
            usort($emailCandidates, function($a, $b) {
                return $b['priority'] - $a['priority'];
            });
            
            return $emailCandidates[0]['email'];
        }
        
        return '';
    }
    
    /**
     * Basic email extraction (wrapper for universal method)
     */
    private function extractEmail($text)
    {
        return $this->extractEmailUniversal($text);
    }
    
    /**
     * Universal phone extraction with context awareness
     */
    private function extractPhoneUniversal($text)
    {
        $lines = explode("\n", $text);
        $phoneCandidates = [];
        
        // Look for phone numbers in the first portion of the resume (header area)
        for ($i = 0; $i < min(15, count($lines)); $i++) {
            $line = trim($lines[$i]);
            
            // Skip section headers that might contain phone numbers
            if (preg_match('/^(education|experience|skills|projects|certifications?|references?|background)[:\s]*$/i', $line)) {
                continue;
            }
            
            $patterns = [
                // Labeled phone numbers
                '/\b(?:Phone|Mobile|Tel|Call|Contact)[:\s]*(\+?[\d\s\-\(\)\.]{7,20})\b/i',
                // International formats
                '/(\+[1-9]\d{1,14})/', // ITU-T E.164 format
                // Indian format (prioritize)
                '/(\+?91[\s\-\.]?[6-9]\d{9})/',
                // US format
                '/(\(?\d{3}\)?[\s\-\.]?\d{3}[\s\-\.]?\d{4})/',
                // UK format
                '/(\+?44[\s\-\.]?\d{4}[\s\-\.]?\d{6})/',
                // Generic mobile (10 digits)
                '/\b([6-9]\d{9})\b/', // Indian mobile without code
                // Common international patterns
                '/(\+?\d{1,4}[\s\-\.\(\)]?\d{1,4}[\s\-\.\(\)]?\d{1,4}[\s\-\.\(\)]?\d{1,4}[\s\-\.\(\)]?\d{1,4})/',
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    $phone = trim($matches[1]);
                    
                    // Clean up phone number
                    $phone = preg_replace('/[^\d\+\-\(\)\.\s]/', '', $phone);
                    $phone = trim($phone);
                    
                    // Skip if too short or too long
                    $digitsOnly = preg_replace('/[^\d]/', '', $phone);
                    if (strlen($digitsOnly) < 7 || strlen($digitsOnly) > 15) {
                        continue;
                    }
                    
                    // Calculate priority score
                    $priority = 100 - ($i * 5);
                    
                    // Reduce priority if line contains section keywords
                    if (preg_match('/\b(education|experience|background|qualification|academic)\b/i', $line)) {
                        $priority -= 30;
                    }
                    
                    // Increase priority for labeled phone numbers
                    if (preg_match('/\b(phone|mobile|tel|call|contact)\b/i', $line)) {
                        $priority += 15;
                    }
                    
                    // Prefer Indian format
                    if (preg_match('/(\+?91[\s\-\.]?[6-9]\d{9})/', $phone)) {
                        $priority += 10;
                    }
                    
                    $phoneCandidates[] = [
                        'phone' => $phone,
                        'priority' => $priority,
                        'line' => $i
                    ];
                    
                    break; // Take first match from this line
                }
            }
        }
        
        // Return the highest priority phone
        if (!empty($phoneCandidates)) {
            usort($phoneCandidates, function($a, $b) {
                return $b['priority'] - $a['priority'];
            });
            
            return $phoneCandidates[0]['phone'];
        }
        
        $bestMatch = '';
        $bestScore = 0;
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $score = $this->scorePhoneNumber($match);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = trim($match);
                    }
                }
            }
        }
        
        return $bestMatch;
    }
    
    /**
     * Extract phone number for international formats
     */
    private function extractPhoneInternational($text)
    {
        // Extended patterns for various countries
        $patterns = [
            '/(\+86[\s\-]?1[3-9]\d{9})/', // China
            '/(\+81[\s\-]?[789]0[\s\-]?\d{8})/', // Japan  
            '/(\+49[\s\-]?1[5-7]\d{8})/', // Germany
            '/(\+33[\s\-]?[67]\d{8})/', // France
            '/(\+7[\s\-]?9\d{9})/', // Russia
            '/(\+55[\s\-]?[1-9]\d{8,9})/', // Brazil
            '/(\+61[\s\-]?4\d{8})/', // Australia
            '/(\+27[\s\-]?[678]\d{8})/', // South Africa
            '/(\+234[\s\-]?[789]\d{9})/', // Nigeria
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractPhoneUniversal($text);
    }
    
    /**
     * Extract name using structured format detection
     */
    private function extractNameStructured($text)
    {
        $lines = explode("\n", $text);
        
        // Look for clear name indicators in structured format
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) continue;
            
            // Look for name with clear separators
            if (preg_match('/^([A-Z][a-z]+(?:\s[A-Z]\.?\s)*[A-Z][a-z]+)[\s]*[-|•·]/', $line, $matches)) {
                return trim($matches[1]);
            }
            
            // Look for centered names (often at top)
            if (strlen($line) < 30 && preg_match('/^\s*([A-Z][a-z]+\s+[A-Z][a-z]+)\s*$/', $line, $matches)) {
                if (!$this->isNotAName($matches[1])) {
                    return trim($matches[1]);
                }
            }
        }
        
        return $this->extractName($text); // Fallback to standard method
    }
    
    /**
     * Extract name from tabular format
     */
    private function extractNameFromTables($text)
    {
        $lines = explode("\n", $text);
        
        // Look for names in table-like structures
        foreach ($lines as $index => $line) {
            $line = trim($line);
            
            // Skip headers and labels
            if (preg_match('/^(Name|Full Name|Candidate|Applicant)[:\s]/i', $line)) {
                // Get the name from the same line or next line
                if (preg_match('/^(?:Name|Full Name|Candidate|Applicant)[:\s]+(.+)$/i', $line, $matches)) {
                    $name = trim($matches[1]);
                    if (!$this->isNotAName($name) && $this->calculateNameScore($name) > 10) {
                        return $name;
                    }
                }
                
                // Check next line
                if (isset($lines[$index + 1])) {
                    $nextLine = trim($lines[$index + 1]);
                    if (!$this->isNotAName($nextLine) && $this->calculateNameScore($nextLine) > 10) {
                        return $nextLine;
                    }
                }
            }
        }
        
        return $this->extractName($text);
    }
    
    /**
     * Extract name for academic format
     */
    private function extractNameAcademic($text)
    {
        // Academic CVs often have name prominently at top
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < min(5, count($lines)); $i++) {
            $line = trim($lines[$i]);
            
            if (empty($line)) continue;
            
            // Academic names might include titles
            if (preg_match('/^(?:Dr\.?\s+|Prof\.?\s+|Mr\.?\s+|Ms\.?\s+|Mrs\.?\s+)?([A-Z][a-z]+(?:\s+[A-Z]\.?\s*)*[A-Z][a-z]+)(?:\s+(?:Ph\.?D\.?|M\.?D\.?|M\.?A\.?|B\.?A\.?))?$/i', $line, $matches)) {
                $name = trim($matches[1]);
                if (!$this->isNotAName($name)) {
                    return $name;
                }
            }
        }
        
        return $this->extractName($text);
    }
    
    /**
     * Extract name for international format
     */
    private function extractNameInternational($text)
    {
        $lines = explode("\n", $text);
        
        // International names might have different patterns
        $patterns = [
            '/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*(?:\s+[A-Z][a-z]+)*)$/', // Standard Western
            '/^([A-Z]{2,}\s+[A-Z][a-z]+)$/', // Surname first (Asian style)
            '/^([A-Z][a-z]+\s+[A-Z]{2,})$/', // Given name first
            '/^([A-Z][a-z]+(?:[-\'][A-Z][a-z]+)*\s+[A-Z][a-z]+)$/', // Hyphenated names
        ];
        
        for ($i = 0; $i < min(5, count($lines)); $i++) {
            $line = trim($lines[$i]);
            
            if (empty($line) || $this->isNotAName($line)) continue;
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $matches)) {
                    return trim($matches[1]);
                }
            }
        }
        
        return $this->extractName($text);
    }
    
    /**
     * Extract name for modern format
     */
    private function extractNameModern($text)
    {
        $lines = explode("\n", $text);
        
        // Modern resumes might have creative formatting
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) continue;
            
            // Look for names with modern separators
            if (preg_match('/([A-Z][a-z]+\s+[A-Z][a-z]+)(?:\s*[|•·◦▪▫]\s*)/', $line, $matches)) {
                if (!$this->isNotAName($matches[1])) {
                    return trim($matches[1]);
                }
            }
            
            // Look for bold or emphasized names (common in modern design)
            if (strlen($line) < 40 && $this->calculateNameScore($line) > 15) {
                return $line;
            }
        }
        
        return $this->extractName($text);
    }

    /**
     * Extract phone number with improved patterns
     */
    private function extractPhone($text)
    {
        // Enhanced patterns for various phone formats
        $patterns = [
            // Indian mobile numbers with country code
            '/(\+91[\s\-]?[6-9]\d{9})/',
            // Standard international format
            '/(\+\d{1,3}[\s\-]?\d{4,15})/',
            // Indian mobile without country code
            '/([6-9]\d{9})/',
            // US format
            '/(\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4}))/',
            // Generic international
            '/(\+?[0-9]{1,4}[\s\-]?[0-9]{3,4}[\s\-]?[0-9]{3,4}[\s\-]?[0-9]{3,4})/',
            // Numbers with parentheses
            '/(\([0-9]{3,4}\)[\s\-]?[0-9]{3,4}[\s\-]?[0-9]{3,4})/'
        ];
        
        $phoneNumbers = [];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleanPhone = preg_replace('/[^\d\+]/', '', $match);
                    
                    // Validate phone number length and format
                    if (strlen($cleanPhone) >= 10 && strlen($cleanPhone) <= 15) {
                        $phoneNumbers[] = [
                            'original' => trim($match),
                            'clean' => $cleanPhone,
                            'length' => strlen($cleanPhone),
                            'score' => $this->scorePhoneNumber($match)
                        ];
                    }
                }
            }
        }
        
        if (!empty($phoneNumbers)) {
            // Sort by score (highest first)
            usort($phoneNumbers, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            
            return $phoneNumbers[0]['original'];
        }
        
        return '';
    }
    
    /**
     * Score phone numbers for better selection
     */
    private function scorePhoneNumber($phone)
    {
        $score = 0;
        
        // Bonus for country code
        if (preg_match('/^\+/', $phone)) {
            $score += 10;
        }
        
        // Bonus for Indian mobile format
        if (preg_match('/\+91/', $phone)) {
            $score += 5;
        }
        
        // Bonus for standard mobile length (10-11 digits)
        $digitCount = strlen(preg_replace('/\D/', '', $phone));
        if ($digitCount == 10 || $digitCount == 11) {
            $score += 5;
        }
        
        // Bonus for proper formatting
        if (preg_match('/[\s\-\(\)]/', $phone)) {
            $score += 2;
        }
        
        return $score;
    }

    /**
     * Extract address with improved detection
     */
    private function extractAddress($text)
    {
        $lines = explode("\n", $text);
        $potentialAddresses = [];
        
        foreach ($lines as $index => $line) {
            $line = trim($line);
            
            // Skip empty lines or very short lines
            if (empty($line) || strlen($line) < 10) {
                continue;
            }
            
            // Skip lines that are clearly contact info (email, phone)
            if (preg_match('/@|EMAIL|MOBILE|PHONE|LINKEDIN/i', $line)) {
                continue;
            }
            
            // Skip lines that are clearly not addresses
            if (preg_match('/^(NAME|EDUCATION|EXPERIENCE|SKILLS|PROJECTS|CERTIFICATION)/i', $line)) {
                continue;
            }
            
            $addressScore = $this->scoreAddressLine($line);
            
            if ($addressScore > 0) {
                $potentialAddresses[] = [
                    'text' => $line,
                    'score' => $addressScore,
                    'position' => $index
                ];
            }
        }
        
        // Look for specific address patterns
        $addressPatterns = [
            '/ADDRESS[:\s]+([^,\n]+(?:,[^,\n]+)*)/i',
            '/ADDR[:\s]+([^,\n]+(?:,[^,\n]+)*)/i',
            '/(?:address|addr)[:\s]*([^,\n]+(?:,[^,\n]+)*)/i'
        ];
        
        foreach ($addressPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $address = trim($matches[1]);
                // Clean up the address
                $address = preg_replace('/^[:\s]+|[:\s]+$/', '', $address);
                
                if (strlen($address) > 10 && !preg_match('/@|MOBILE|PHONE|EMAIL/i', $address)) {
                    $potentialAddresses[] = [
                        'text' => $address,
                        'score' => 20, // High score for pattern match
                        'position' => 0
                    ];
                }
            }
        }
        
        if (!empty($potentialAddresses)) {
            // Sort by score (highest first), then by position (earliest first)
            usort($potentialAddresses, function($a, $b) {
                if ($a['score'] === $b['score']) {
                    return $a['position'] - $b['position'];
                }
                return $b['score'] - $a['score'];
            });
            
            return $potentialAddresses[0]['text'];
        }
        
        return '';
    }
    
    /**
     * Score address lines for better detection
     */
    private function scoreAddressLine($line)
    {
        $score = 0;
        
        // Bonus for common address keywords
        $addressKeywords = ['street', 'st', 'avenue', 'ave', 'road', 'rd', 'drive', 'dr', 
                           'lane', 'ln', 'blvd', 'boulevard', 'city', 'state', 'zip',
                           'nagar', 'colony', 'sector', 'block', 'flat', 'apartment', 'apt'];
        
        foreach ($addressKeywords as $keyword) {
            if (stripos($line, $keyword) !== false) {
                $score += 5;
            }
        }
        
        // Bonus for postal/ZIP codes
        if (preg_match('/\b\d{5}(-\d{4})?\b|\b[A-Za-z]\d[A-Za-z] ?\d[A-Za-z]\d\b|\b\d{6}\b/', $line)) {
            $score += 8;
        }
        
        // Bonus for having multiple address components (numbers, words, commas)
        if (preg_match('/\d+/', $line) && str_word_count($line) >= 3) {
            $score += 5;
        }
        
        // Bonus for commas (address separator)
        if (substr_count($line, ',') >= 1) {
            $score += 3;
        }
        
        // Penalty for email/phone patterns
        if (preg_match('/@|\+\d{2}|EMAIL|MOBILE|PHONE/i', $line)) {
            $score -= 20;
        }
        
        // Penalty for all caps (likely headers)
        if ($line === strtoupper($line) && strlen($line) > 15) {
            $score -= 5;
        }
        
        return max(0, $score);
    }

    /**
     * Extract professional summary/objective
     */
    private function extractSummary($text)
    {
        $keywords = ['summary', 'objective', 'profile', 'about', 'overview'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($keywords as $keyword) {
                if (stripos($line, $keyword) !== false && strlen($line) < 50) {
                    // Found a summary header, get the next few lines
                    $summary = '';
                    for ($j = $i + 1; $j < min($i + 10, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        // Stop if we hit another section
                        if (preg_match('/(experience|education|skills|work|employment)/i', $nextLine) && strlen($nextLine) < 30) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $summary .= $nextLine . ' ';
                        }
                    }
                    
                    if (strlen($summary) > 20) {
                        return trim($summary);
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Extract skills
     */
    private function extractSkills($text)
    {
        $skillsKeywords = ['skills', 'technical skills', 'core competencies', 'expertise'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($skillsKeywords as $keyword) {
                if (stripos($line, $keyword) !== false && strlen($line) < 50) {
                    // Found skills section, extract next few lines
                    $skills = '';
                    for ($j = $i + 1; $j < min($i + 15, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        // Stop if we hit another section
                        if (preg_match('/(experience|education|work|employment|projects)/i', $nextLine) && strlen($nextLine) < 30) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $skills .= $nextLine . ', ';
                        }
                    }
                    
                    if (strlen($skills) > 5) {
                        return trim($skills, ', ');
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Extract work experience
     */
    private function extractExperience($text)
    {
        $experienceKeywords = ['experience', 'employment', 'work history', 'career', 'professional experience'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($experienceKeywords as $keyword) {
                if (stripos($line, $keyword) !== false && strlen($line) < 50) {
                    // Found experience section
                    $experience = '';
                    for ($j = $i + 1; $j < min($i + 30, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        // Stop if we hit another section
                        if (preg_match('/(education|skills|projects|certifications)/i', $nextLine) && strlen($nextLine) < 30) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $experience .= $nextLine . "\n";
                        }
                    }
                    
                    if (strlen($experience) > 20) {
                        return trim($experience);
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Extract education information
     */
    private function extractEducation($text)
    {
        $educationKeywords = ['education', 'academic', 'qualifications', 'degree', 'university', 'college'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($educationKeywords as $keyword) {
                if (stripos($line, $keyword) !== false && strlen($line) < 50) {
                    // Found education section
                    $education = '';
                    for ($j = $i + 1; $j < min($i + 15, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        // Stop if we hit another section
                        if (preg_match('/(experience|skills|projects|certifications|references)/i', $nextLine) && strlen($nextLine) < 30) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $education .= $nextLine . "\n";
                        }
                    }
                    
                    if (strlen($education) > 10) {
                        return trim($education);
                    }
                }
            }
        }
        
        return '';
    }
}
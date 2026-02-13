<?php

namespace App\Services;

/**
 * Additional parsing methods for different resume formats
 * This file contains format-specific parsing methods to keep the main service clean
 */
trait ResumeFormatParsers
{
    /**
     * Extract address using structured format detection
     */
    private function extractAddressStructured($text)
    {
        $patterns = [
            '/Address[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
            '/Location[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
            '/Home[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $address = trim($matches[1]);
                if (strlen($address) > 10) {
                    return $address;
                }
            }
        }
        
        return $this->extractAddress($text);
    }
    
    /**
     * Extract address from table format
     */
    private function extractAddressFromTables($text)
    {
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            // Look for postal codes or address indicators
            if (preg_match('/\b\d{5,6}\b|\b\d{3}\s*-\s*\d{3}\b/', $line) && !preg_match('/@|EMAIL|MOBILE/i', $line)) {
                return trim($line);
            }
        }
        
        return $this->extractAddress($text);
    }
    
    /**
     * Extract address for academic format
     */
    private function extractAddressAcademic($text)
    {
        // Academic addresses might include institution affiliation
        $patterns = [
            '/(?:Address|Affiliation)[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
            '/(?:Department|Institute|University)[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractAddress($text);
    }
    
    /**
     * Extract address for international format
     */
    private function extractAddressInternational($text)
    {
        // International addresses might have different postal code formats
        $patterns = [
            '/\b[A-Z]{1,2}\d{1,2}\s?\d[A-Z]{2}\b/', // UK postcodes
            '/\b\d{5}-\d{4}\b/', // US ZIP+4
            '/\b[A-Z]\d[A-Z]\s?\d[A-Z]\d\b/', // Canadian postal codes
            '/\b\d{5}\b/', // Standard 5-digit codes
        ];
        
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) && !preg_match('/@|EMAIL|MOBILE/i', $line)) {
                    return trim($line);
                }
            }
        }
        
        return $this->extractAddress($text);
    }
    
    /**
     * Extract address for modern format
     */
    private function extractAddressModern($text)
    {
        // Modern resumes might use icons or symbols
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            // Look for location symbols or indicators
            if (preg_match('/[📍🏠🌍🌎🌏]|location|address/i', $line)) {
                // Clean up the line
                $cleaned = preg_replace('/[📍🏠🌍🌎🌏]|\bLocation\b|\bAddress\b/i', '', $line);
                $cleaned = trim($cleaned, ' :•-|');
                
                if (strlen($cleaned) > 10) {
                    return $cleaned;
                }
            }
        }
        
        return $this->extractAddress($text);
    }
    
    /**
     * Extract summary using structured format
     */
    private function extractSummaryStructured($text)
    {
        $keywords = ['summary', 'profile', 'objective', 'overview', 'about', 'introduction'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($keywords as $keyword) {
                if (preg_match("/^{$keyword}[:\s-]*$/i", $line)) {
                    // Found header, collect content from next lines
                    $summary = '';
                    for ($j = $i + 1; $j < min($i + 15, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        // Stop at next section
                        if (preg_match('/^(experience|education|skills|work|employment|projects|certifications?)[:\s]*$/i', $nextLine)) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $summary .= $nextLine . ' ';
                        }
                    }
                    
                    if (strlen($summary) > 50) {
                        return trim($summary);
                    }
                }
            }
        }
        
        return $this->extractSummary($text);
    }
    
    /**
     * Extract summary from tables
     */
    private function extractSummaryFromTables($text)
    {
        // Look for summary in table format
        if (preg_match('/(?:Summary|Profile|Objective)[:\s]*([^,\n]{50,})/i', $text, $matches)) {
            return trim($matches[1]);
        }
        
        return $this->extractSummary($text);
    }
    
    /**
     * Extract summary for academic format
     */
    private function extractSummaryAcademic($text)
    {
        $keywords = ['research interests', 'research focus', 'abstract', 'biography', 'profile'];
        
        foreach ($keywords as $keyword) {
            if (preg_match("/{$keyword}[:\s]*([^,\n]{50,})/i", $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractSummary($text);
    }
    
    /**
     * Extract summary for international format
     */
    private function extractSummaryInternational($text)
    {
        // International resumes might use different terms
        $keywords = ['personal statement', 'career summary', 'professional profile', 'career objective'];
        
        foreach ($keywords as $keyword) {
            if (preg_match("/{$keyword}[:\s]*([^,\n]{50,})/i", $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractSummary($text);
    }
    
    /**
     * Extract summary for modern format
     */
    private function extractSummaryModern($text)
    {
        $lines = explode("\n", $text);
        
        // Look for paragraph-style summaries without headers
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Look for descriptive sentences (modern resumes often start with summary)
            if (strlen($line) > 100 && 
                preg_match('/^[A-Z][a-z]/', $line) && 
                !preg_match('/^(Name|Email|Phone|Address)/i', $line)) {
                
                // Check if it looks like a summary
                if (preg_match('/\b(experienced|skilled|professional|passionate|dedicated|results|proven)\b/i', $line)) {
                    return $line;
                }
            }
        }
        
        return $this->extractSummary($text);
    }
    
    /**
     * Extract skills using structured format
     */
    private function extractSkillsStructured($text)
    {
        $skillsKeywords = ['skills', 'technical skills', 'core competencies', 'expertise', 'technologies', 'tools'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($skillsKeywords as $keyword) {
                if (preg_match("/^{$keyword}[:\s-]*$/i", $line)) {
                    $skills = '';
                    for ($j = $i + 1; $j < min($i + 20, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        if (preg_match('/^(experience|education|work|employment|projects|certifications?)[:\s]*$/i', $nextLine)) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $skills .= $nextLine . ', ';
                        }
                    }
                    
                    if (strlen($skills) > 10) {
                        return trim($skills, ', ');
                    }
                }
            }
        }
        
        return $this->extractSkills($text);
    }
    
    /**
     * Extract skills from tables
     */
    private function extractSkillsFromTables($text)
    {
        // Look for skills in table format with categories
        if (preg_match('/(?:Technical Skills?|Programming|Languages?|Tools?)[:\s]*([^,\n]+(?:,[^,\n]+)*)/i', $text, $matches)) {
            return trim($matches[1]);
        }
        
        return $this->extractSkills($text);
    }
    
    /**
     * Extract skills for academic format
     */
    private function extractSkillsAcademic($text)
    {
        $keywords = ['research skills', 'methodologies', 'software', 'statistical tools', 'laboratory skills'];
        
        foreach ($keywords as $keyword) {
            if (preg_match("/{$keyword}[:\s]*([^,\n]+(?:,[^,\n]+)*)/i", $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractSkills($text);
    }
    
    /**
     * Extract skills for international format
     */
    private function extractSkillsInternational($text)
    {
        // International formats might include languages
        $patterns = [
            '/(?:Core Skills?|Key Skills?|Competencies)[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
            '/(?:Languages?)[:\s]*([^,\n]+(?:,[^,\n]+)*)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractSkills($text);
    }
    
    /**
     * Extract skills for modern format
     */
    private function extractSkillsModern($text)
    {
        // Modern resumes might list skills with symbols or in creative formats
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            // Look for bullet points or symbols
            if (preg_match('/[•·▪▫◦‣⁃]\s*(.+)/', $line, $matches)) {
                $content = trim($matches[1]);
                
                // Check if it looks like skills
                if (preg_match('/\b(Java|Python|JavaScript|HTML|CSS|SQL|React|Angular|Vue|Node|PHP|C\+\+|C#|Ruby|Swift|Kotlin|Go|Rust)\b/i', $content)) {
                    return $content;
                }
            }
        }
        
        return $this->extractSkills($text);
    }
    
    /**
     * Extract experience using structured format
     */
    private function extractExperienceStructured($text)
    {
        $experienceKeywords = ['experience', 'employment', 'work history', 'career', 'professional experience', 'work experience'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($experienceKeywords as $keyword) {
                if (preg_match("/^{$keyword}[:\s-]*$/i", $line)) {
                    $experience = '';
                    for ($j = $i + 1; $j < min($i + 50, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        if (preg_match('/^(education|skills|projects|certifications?|references?)[:\s]*$/i', $nextLine)) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $experience .= $nextLine . "\n";
                        }
                    }
                    
                    if (strlen($experience) > 50) {
                        return trim($experience);
                    }
                }
            }
        }
        
        return $this->extractExperience($text);
    }
    
    /**
     * Extract experience from tables
     */
    private function extractExperienceFromTables($text)
    {
        // Look for experience in tabular format
        $patterns = [
            '/(?:Company|Organization|Employer)[:\s]*([^,\n]+)/i',
            '/(?:Position|Role|Title)[:\s]*([^,\n]+)/i',
            '/(?:Duration|Period|From|To)[:\s]*([0-9]{4}[^,\n]*)/i',
        ];
        
        $experience = '';
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $experience .= trim($match) . "\n";
                }
            }
        }
        
        if (strlen($experience) > 20) {
            return trim($experience);
        }
        
        return $this->extractExperience($text);
    }
    
    /**
     * Extract experience for academic format
     */
    private function extractExperienceAcademic($text)
    {
        $keywords = ['academic positions', 'teaching experience', 'research experience', 'professional appointments', 'employment history'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $experience = '';
                    for ($j = $i + 1; $j < min($i + 40, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        if (preg_match('/^(education|skills|projects|certifications?|publications)[:\s]*$/i', $nextLine)) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $experience .= $nextLine . "\n";
                        }
                    }
                    
                    if (strlen($experience) > 30) {
                        return trim($experience);
                    }
                }
            }
        }
        
        return $this->extractExperience($text);
    }
    
    /**
     * Extract experience for international format
     */
    private function extractExperienceInternational($text)
    {
        $keywords = ['career history', 'professional background', 'work background', 'employment record'];
        
        foreach ($keywords as $keyword) {
            if (preg_match("/{$keyword}[:\s-]*([^,\n]{50,})/i", $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $this->extractExperience($text);
    }
    
    /**
     * Extract experience for modern format
     */
    private function extractExperienceModern($text)
    {
        $lines = explode("\n", $text);
        
        // Look for modern experience formatting
        $experience = '';
        $foundExperience = false;
        
        foreach ($lines as $line) {
            // Look for company names with dates
            if (preg_match('/([A-Z][a-zA-Z\s&,.-]+)\s+[|\-•]\s*([0-9]{4}[-\s]*[0-9]{4}|[0-9]{4}[-\s]*present)/i', $line, $matches)) {
                $experience .= $line . "\n";
                $foundExperience = true;
            }
            // Look for job titles with company indicators
            elseif (preg_match('/([A-Z][a-zA-Z\s]+Engineer|Manager|Developer|Analyst|Specialist)[^,\n]*@\s*([A-Z][a-zA-Z\s&,.-]+)/i', $line, $matches)) {
                $experience .= $line . "\n";
                $foundExperience = true;
            }
        }
        
        if ($foundExperience && strlen($experience) > 20) {
            return trim($experience);
        }
        
        return $this->extractExperience($text);
    }
    
    /**
     * Extract education using structured format
     */
    private function extractEducationStructured($text)
    {
        $educationKeywords = ['education', 'academic', 'qualifications', 'degrees', 'university', 'college', 'school'];
        $lines = explode("\n", $text);
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            foreach ($educationKeywords as $keyword) {
                if (preg_match("/^{$keyword}[:\s-]*$/i", $line)) {
                    $education = '';
                    for ($j = $i + 1; $j < min($i + 30, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        
                        if (preg_match('/^(experience|skills|work|employment|projects|certifications?)[:\s]*$/i', $nextLine)) {
                            break;
                        }
                        
                        if (!empty($nextLine)) {
                            $education .= $nextLine . "\n";
                        }
                    }
                    
                    if (strlen($education) > 20) {
                        return trim($education);
                    }
                }
            }
        }
        
        return $this->extractEducation($text);
    }
    
    /**
     * Extract education from tables
     */
    private function extractEducationFromTables($text)
    {
        // Look for education in tabular format
        $patterns = [
            '/(?:Degree|Course|Program)[:\s]*([^,\n]+)/i',
            '/(?:Institution|University|College|School)[:\s]*([^,\n]+)/i',
            '/(?:Year|Graduation|Completed)[:\s]*([0-9]{4}[^,\n]*)/i',
            '/(?:CGPA|GPA|Marks|Grade|Percentage)[:\s]*([0-9\.]+[^,\n]*)/i',
        ];
        
        $education = '';
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $education .= trim($match) . "\n";
                }
            }
        }
        
        if (strlen($education) > 15) {
            return trim($education);
        }
        
        return $this->extractEducation($text);
    }
    
    /**
     * Extract education for academic format
     */
    private function extractEducationAcademic($text)
    {
        $keywords = ['academic background', 'educational background', 'degrees earned', 'academic qualifications'];
        
        foreach ($keywords as $keyword) {
            if (preg_match("/{$keyword}[:\s-]*([^,\n]{30,})/i", $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // Look for specific academic degree patterns
        if (preg_match('/(Ph\.?D\.?|Doctor|M\.?A\.?|M\.?S\.?|Master|B\.?A\.?|B\.?S\.?|Bachelor)[^,\n]{10,}/i', $text, $matches)) {
            return trim($matches[0]);
        }
        
        return $this->extractEducation($text);
    }
    
    /**
     * Extract education for international format
     */
    private function extractEducationInternational($text)
    {
        // International education systems
        $keywords = ['academic credentials', 'educational qualifications', 'academic history', 'study background'];
        
        foreach ($keywords as $keyword) {
            if (preg_match("/{$keyword}[:\s-]*([^,\n]{30,})/i", $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        // International degree patterns
        $patterns = [
            '/(Honours?|Honors?)\s+[A-Z][a-zA-Z\s]+/i',
            '/Level\s+[0-9]+\s+[A-Z][a-zA-Z\s]+/i',
            '/(Diploma|Certificate|Licence|License)\s+[A-Z][a-zA-Z\s]+/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[0]);
            }
        }
        
        return $this->extractEducation($text);
    }
    
    /**
     * Extract education for modern format
     */
    private function extractEducationModern($text)
    {
        $lines = explode("\n", $text);
        
        // Look for modern education formatting
        foreach ($lines as $line) {
            // University with graduation year
            if (preg_match('/([A-Z][a-zA-Z\s&,.-]*University[^,\n]*)\s+[|\-•]\s*([0-9]{4})/i', $line, $matches)) {
                return trim($line);
            }
            
            // Degree with institution
            if (preg_match('/(Bachelor|Master|B\.?\s*[A-Z]|M\.?\s*[A-Z])[^,\n]+@\s*([A-Z][a-zA-Z\s&,.-]+)/i', $line, $matches)) {
                return trim($line);
            }
        }
        
        return $this->extractEducation($text);
    }
}
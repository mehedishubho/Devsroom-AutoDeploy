<?php

/**
 * Security Scanner class.
 *
 * @package Devsoom_AutoDeploy
 */

namespace Devsoom_AutoDeploy\Core;

/**
 * Class Security_Scanner
 *
 * Scans plugin files for security issues.
 *
 * @since 1.0.0
 */
class Security_Scanner
{

    /**
     * Basic scan patterns.
     *
     * @var array
     */
    private array $basic_patterns = array(
        '/\beval\s*\(/i',
        '/\bassert\s*\(/i',
        '/\bcreate_function\s*\(/i',
        '/\bpreg_replace\s*\(\s*[\'"].*\/e/i',
        '/\bbase64_decode\s*\(/i',
        '/\bstr_rot13\s*\(/i',
        '/\bgzinflate\s*\(/i',
        '/\bstrrev\s*\(/i',
        '/\bsystem\s*\(/i',
        '/\bexec\s*\(/i',
        '/\bshell_exec\s*\(/i',
        '/\bpassthru\s*\(/i',
        '/\bproc_open\s*\(/i',
        '/\bpopen\s*\(/i',
        '/\bfile_get_contents\s*\(\s*[\'"]https?:\/\//i',
        '/\bfsockopen\s*\(/i',
        '/\bcurl_exec\s*\(/i',
        '/\bcurl_multi_exec\s*\(/i',
        '/\bparse_url\s*\(\s*\$_/i',
        '/\bextract\s*\(\s*\$/i',
        '/\bimport_request_variables\s*\(/i',
    );

    /**
     * Advanced scan patterns.
     *
     * @var array
     */
    private array $advanced_patterns = array(
        // Obfuscated code patterns.
        '/\$\w+\s*=\s*["\'][\w\/\+\=]+["\'];/',
        '/\$_[A-Z]+\s*\[.*\]\s*\(/',
        '/\bchr\s*\(/i',
        '/\bord\s*\(/i',
        '/\bpack\s*\(/i',
        // Variable function calls.
        '/\$\w+\s*\(/',
        // Dynamic includes.
        '/include\s*\(\s*\$/i',
        '/require\s*\(\s*\$/i',
        '/include_once\s*\(\s*\$/i',
        '/require_once\s*\(\s*\$/i',
        // Suspicious variable names.
        '/\$\{.*\}/',
        '/\bGLOBALS\s*\[/',
        // Backticks execution.
        '/`[^`]+`/',
    );

    /**
     * Malware signatures.
     *
     * @var array
     */
    private array $malware_signatures = array(
        'base64_decode',
        'shell_exec',
        'eval(',
        'gzuncompress',
        'str_rot13',
        'create_function',
        'preg_replace.*\/e',
        'chr(',
    );

    /**
     * Scan a directory for security issues.
     *
     * @param string $directory   Directory to scan.
     * @param string $scan_level  Scan level (basic, advanced).
     * @return array Scan results.
     */
    public function scan_directory(string $directory, string $scan_level = 'basic'): array
    {
        $results = array(
            'status'      => 'passed',
            'issues'      => array(),
            'scanned'     => 0,
            'errors'      => 0,
            'warnings'    => 0,
        );

        if (! is_dir($directory)) {
            $results['status'] = 'error';
            $results['issues'][] = array(
                'type'    => 'error',
                'message' => 'Directory not found: ' . $directory,
            );
            return $results;
        }

        $files = $this->get_php_files($directory);

        foreach ($files as $file) {
            $result = $this->scan_file($file, $scan_level);
            $results['scanned']++;

            if (! empty($result['issues'])) {
                $results['status'] = 'failed';
                $results['issues']  = array_merge($results['issues'], $result['issues']);
                $results['errors']  += count($result['issues']);
            }

            if (! empty($result['warnings'])) {
                $results['warnings'] += count($result['warnings']);
            }
        }

        return $results;
    }

    /**
     * Scan a single file for security issues.
     *
     * @param string $file       File path.
     * @param string $scan_level Scan level (basic, advanced).
     * @return array Scan results.
     */
    public function scan_file(string $file, string $scan_level = 'basic'): array
    {
        $results = array(
            'file'     => $file,
            'issues'   => array(),
            'warnings' => array(),
        );

        if (! file_exists($file)) {
            $results['issues'][] = array(
                'type'    => 'error',
                'message' => 'File not found',
                'file'    => $file,
            );
            return $results;
        }

        $content = file_get_contents($file);

        if (false === $content) {
            $results['issues'][] = array(
                'type'    => 'error',
                'message' => 'Could not read file',
                'file'    => $file,
            );
            return $results;
        }

        // Run basic scan.
        foreach ($this->basic_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $line = $this->find_line_number($content, $matches[0]);
                $results['issues'][] = array(
                    'type'    => 'error',
                    'message' => 'Suspicious function detected: ' . $matches[0],
                    'file'    => $file,
                    'line'    => $line,
                    'pattern' => $pattern,
                );
            }
        }

        // Run advanced scan if requested.
        if ('advanced' === $scan_level) {
            foreach ($this->advanced_patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $line = $this->find_line_number($content, $matches[0]);
                    $results['issues'][] = array(
                        'type'    => 'error',
                        'message' => 'Advanced suspicious pattern detected: ' . $matches[0],
                        'file'    => $file,
                        'line'    => $line,
                        'pattern' => $pattern,
                    );
                }
            }

            // Check for malware signatures.
            foreach ($this->malware_signatures as $signature) {
                if (stripos($content, $signature) !== false) {
                    $line = $this->find_line_number($content, $signature);
                    $results['issues'][] = array(
                        'type'    => 'error',
                        'message' => 'Malware signature detected: ' . $signature,
                        'file'    => $file,
                        'line'    => $line,
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Get all PHP files in a directory recursively.
     *
     * @param string $directory Directory to scan.
     * @return array Array of file paths.
     */
    private function get_php_files(string $directory): array
    {
        $files = array();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 'php' === strtolower($file->getExtension())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Find the line number of a match in content.
     *
     * @param string $content File content.
     * @param string $match   Match to find.
     * @return int Line number.
     */
    private function find_line_number(string $content, string $match): int
    {
        $lines   = explode("\n", $content);
        $match   = substr($match, 0, 50); // Use first 50 chars for matching.
        $line_no = 1;

        foreach ($lines as $line) {
            if (strpos($line, $match) !== false) {
                return $line_no;
            }
            $line_no++;
        }

        return 0;
    }

    /**
     * Generate a scan report.
     *
     * @param array $results Scan results.
     * @return string Formatted report.
     */
    public function generate_report(array $results): string
    {
        $report = "Security Scan Report\n";
        $report .= "====================\n\n";
        $report .= "Status: " . strtoupper($results['status']) . "\n";
        $report .= "Files Scanned: " . $results['scanned'] . "\n";
        $report .= "Errors Found: " . $results['errors'] . "\n";
        $report .= "Warnings: " . $results['warnings'] . "\n\n";

        if (! empty($results['issues'])) {
            $report .= "Issues:\n";
            $report .= "-------\n";

            foreach ($results['issues'] as $issue) {
                $report .= sprintf(
                    "[%s] %s\n",
                    strtoupper($issue['type']),
                    $issue['message']
                );

                if (isset($issue['file'])) {
                    $report .= "File: " . $issue['file'] . "\n";
                }

                if (isset($issue['line']) && $issue['line'] > 0) {
                    $report .= "Line: " . $issue['line'] . "\n";
                }

                $report .= "\n";
            }
        }

        return $report;
    }
}

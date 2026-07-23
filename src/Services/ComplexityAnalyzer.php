<?php

declare(strict_types=1);

namespace VendorName\Canvas\Services;

use VendorName\Canvas\Data\Node;

class ComplexityAnalyzer
{
    public function analyze(Node $node): int
    {
        $score = 1;
        $source = $node->getSourceCode();

        if ($source === null) {
            return $score;
        }

        $score += $this->countConditionals($source) * 1;
        $score += $this->countLoops($source) * 2;
        $score += $this->countTryCatch($source) * 2;
        $score += $this->countMethodCalls($source) * 0.5;
        $score += $this->countNestedFunctions($source) * 3;

        $methods = $node->getMetadata('methods', []);
        $score += count($methods) * 2;

        return (int) round($score);
    }

    public function getCyclomaticComplexity(Node $node): int
    {
        $source = $node->getSourceCode();
        if ($source === null) {
            return 1;
        }

        $complexity = 1;

        $tokens = token_get_all($source);

        foreach ($tokens as $token) {
            if (! is_array($token)) {
                continue;
            }

            switch ($token[0]) {
                case T_IF:
                case T_ELSEIF:
                case T_WHILE:
                case T_FOR:
                case T_FOREACH:
                case T_CASE:
                case T_CATCH:
                case T_BOOLEAN_AND:
                case T_BOOLEAN_OR:
                case T_LOGICAL_AND:
                case T_LOGICAL_OR:
                    $complexity++;
                    break;
            }
        }

        return $complexity;
    }

    private function countConditionals(string $source): int
    {
        preg_match_all('/\b(if|elseif|else|switch|case)\b/', $source, $matches);

        return count($matches[0]);
    }

    private function countLoops(string $source): int
    {
        preg_match_all('/\b(for|foreach|while|do\s*\{)\b/', $source, $matches);

        return count($matches[0]);
    }

    private function countTryCatch(string $source): int
    {
        preg_match_all('/\b(try|catch|finally)\b/', $source, $matches);

        return count($matches[0]);
    }

    private function countMethodCalls(string $source): int
    {
        preg_match_all('/->(\w+)\s*\(/', $source, $matches);

        return count($matches[1]);
    }

    private function countNestedFunctions(string $source): int
    {
        preg_match_all('/function\s+\(/', $source, $matches);

        return count($matches[0]);
    }
}

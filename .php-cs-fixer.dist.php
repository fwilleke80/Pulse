<?php

/**
 * @file .php-cs-fixer.dist.php
 * @brief PHP-CS-Fixer rules preserving Pulse's tab-indented Allman style.
 * @author Frank Willeke
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
	->in([__DIR__ . '/app', __DIR__ . '/public', __DIR__ . '/tests', __DIR__ . '/tools'])
	->append([__DIR__ . '/bootstrap.php']);

return (new PhpCsFixer\Config())
	->setRiskyAllowed(false)
	->setIndent("\t")
	->setLineEnding("\n")
	->setRules([
		'@PER-CS' => true,
		'array_syntax' => ['syntax' => 'short'],
		'braces_position' => [
			'allow_single_line_anonymous_functions' => false,
			'allow_single_line_empty_anonymous_classes' => false,
			'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
			'anonymous_functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
			'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
			'control_structures_opening_brace' => 'next_line_unless_newline_at_signature_end',
			'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
		],
		'declare_strict_types' => true,
		'no_unused_imports' => true,
		'single_quote' => true,
	])
	->setFinder($finder);

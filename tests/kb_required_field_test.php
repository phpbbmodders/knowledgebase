<?php
namespace phpbbmodders\knowledgebase\inc;

require_once __DIR__ . '/kb_extensions_test.php';
require_once dirname(__DIR__) . '/inc/functions_kb.php';

class kb_required_field_test extends \PHPUnit\Framework\TestCase
{
	private const VALUES = [['value_id' => '4'], ['value_id' => '5']];

	/** @dataProvider cases */
	public function test_a_required_field_is_missing_only_when_no_enabled_value_was_chosen(array $field, array $values, array $submitted, bool $missing)
	{
		$this->assertSame($missing, functions_kb::is_required_field_missing($field, $values, $submitted));
	}

	public function cases(): array
	{
		return [
			'optional field, nothing chosen'            => [['field_required' => '0'], self::VALUES, [], false],
			'required, nothing chosen'                  => [['field_required' => '1'], self::VALUES, [], true],
			'required, one value chosen'                => [['field_required' => '1'], self::VALUES, [5], false],
			'required, all values chosen'               => [['field_required' => 1], self::VALUES, [4, 5], false],
			'required, ids arrive as strings'           => [['field_required' => '1'], self::VALUES, ['4'], false],
			'required, only a made-up id chosen'        => [['field_required' => '1'], self::VALUES, [999], true],
			'required, a disabled value chosen'         => [['field_required' => '1'], [['value_id' => '4']], [5], true],
			'required, one real among made-up ids'      => [['field_required' => '1'], self::VALUES, [999, 4], false],
			'required but no enabled values to choose'  => [['field_required' => '1'], [], [], false],
			'flag missing from the row'                 => [[], self::VALUES, [], false],
			'flag null'                                 => [['field_required' => null], self::VALUES, [], false],
		];
	}
}

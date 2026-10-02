<?php

class Email_shell_test extends CI_TestCase {

	/**
	 * @var	string
	 */
	protected $sentinel;

	public function set_up()
	{
		$ci = $this->ci_instance();
		$ci->lang = $this->getMockBuilder('CI_Lang')->setMethods(array('load', 'line'))->getMock();
		$ci->lang->expects($this->any())->method('line')->will($this->returnValue(FALSE));

		$this->sentinel = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_email_shell_'.getmypid().'_'.mt_rand().'.sentinel';
	}

	// --------------------------------------------------------------------

	public function tear_down()
	{
		if ($this->sentinel !== '' AND file_exists($this->sentinel))
		{
			unlink($this->sentinel);
		}
	}

	// --------------------------------------------------------------------

	public function test_send_with_sendmail_escapes_mailpath()
	{
		if ( ! function_usable('popen'))
		{
			$this->markTestSkipped('popen is not available.');
		}

		if (file_exists($this->sentinel))
		{
			unlink($this->sentinel);
		}

		$email = new CI_Email();
		$email->set_header('From', 'sender@example.com');
		$email->mailpath = '/missing-sendmail; touch '.$this->sentinel.'; #';

		$method = new ReflectionMethod('CI_Email', '_send_with_sendmail');
		PHP_VERSION_ID < 80100 && $method->setAccessible(TRUE);

		$this->assertFalse($method->invoke($email));
		$this->assertFileDoesNotExist($this->sentinel);
	}

}

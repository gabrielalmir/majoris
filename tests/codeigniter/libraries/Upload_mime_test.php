<?php

class Upload_mime_test extends CI_TestCase {

	/**
	 * @var	CI_Upload
	 */
	public $upload;

	public function set_up()
	{
		$ci = $this->ci_instance();
		$ci->upload = new CI_Upload();
		$ci->security = new Mock_Core_Security('UTF-8');
		$ci->lang = $this->getMockBuilder('CI_Lang')->setMethods(array('load', 'line'))->getMock();
		$ci->lang->expects($this->any())->method('line')->will($this->returnValue(FALSE));
		$this->upload = $ci->upload;
	}

	// --------------------------------------------------------------------

	public function test__file_mime_type_does_not_trust_client_type()
	{
		$file = array(
			'tmp_name' => '/tmp/majoris-upload-mime-missing-' . uniqid('', TRUE),
			'type' => 'application/x-php'
		);

		$method = new ReflectionMethod($this->upload, '_file_mime_type');
		PHP_VERSION_ID < 80100 && $method->setAccessible(TRUE);
		$method->invoke($this->upload, $file);

		$this->assertSame('', $this->upload->file_type);
		$this->assertNotSame($file['type'], $this->upload->file_type);
	}

}

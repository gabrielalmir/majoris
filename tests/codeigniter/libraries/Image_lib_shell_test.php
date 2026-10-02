<?php

class Image_lib_shell_test extends CI_TestCase {

	/**
	 * @var	string
	 */
	protected $sentinel;

	/**
	 * @var	string
	 */
	protected $source_path;

	/**
	 * @var	string
	 */
	protected $dest_path;

	/**
	 * @var	string
	 */
	protected $netpbm_prefix;

	/**
	 * @var	string
	 */
	protected $netpbm_temp_path;

	public function set_up()
	{
		$ci = $this->ci_instance();
		$ci->lang = $this->getMockBuilder('CI_Lang')->setMethods(array('load', 'line'))->getMock();
		$ci->lang->expects($this->any())->method('line')->will($this->returnValue(FALSE));

		$this->sentinel = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_image_shell_'.getmypid().'_'.mt_rand().'.sentinel';
		$this->source_path = tempnam(sys_get_temp_dir(), 'ci_image_src_');
		$this->dest_path = tempnam(sys_get_temp_dir(), 'ci_image_dst_');

		if ($this->source_path === FALSE OR $this->dest_path === FALSE)
		{
			$this->markTestSkipped('Unable to create temporary image files.');
		}

		file_put_contents($this->source_path, 'image');
	}

	// --------------------------------------------------------------------

	public function tear_down()
	{
		foreach (array($this->sentinel, $this->source_path, $this->dest_path, $this->netpbm_prefix, $this->netpbm_temp_path) as $file)
		{
			if ($file !== NULL AND $file !== FALSE AND $file !== '' AND file_exists($file))
			{
				unlink($file);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * @param	string	$library_path
	 * @return	CI_Image_lib
	 */
	protected function create_image_lib($library_path)
	{
		$image = new CI_Image_lib();
		$image->library_path = $library_path;
		$image->full_src_path = $this->source_path;
		$image->full_dst_path = $this->dest_path;
		$image->width = 40;
		$image->height = 20;
		$image->x_axis = 1;
		$image->y_axis = 2;
		$image->quality = 90;
		$image->maintain_ratio = FALSE;
		$image->image_type = 2;

		return $image;
	}

	// --------------------------------------------------------------------

	public function test_image_process_imagemagick_escapes_library_path()
	{
		if (file_exists($this->sentinel))
		{
			unlink($this->sentinel);
		}

		$image = $this->create_image_lib('/missing-imagemagick; touch '.$this->sentinel.'; #');

		$this->assertFalse($image->image_process_imagemagick('resize'));
		$this->assertFileDoesNotExist($this->sentinel);
	}

	// --------------------------------------------------------------------

	public function test_image_process_imagemagick_rejects_non_integer_fields()
	{
		$fields = array(
			'width' => '40; touch '.$this->sentinel.'; #',
			'height' => '20; touch '.$this->sentinel.'; #',
			'x_axis' => '1; touch '.$this->sentinel.'; #',
			'y_axis' => '2; touch '.$this->sentinel.'; #'
		);

		foreach ($fields as $field => $value)
		{
			if (file_exists($this->sentinel))
			{
				unlink($this->sentinel);
			}

			$image = $this->create_image_lib('/missing-imagemagick');
			$image->{$field} = $value;

			$this->assertFalse($image->image_process_imagemagick('resize'), $field);
			$this->assertFileDoesNotExist($this->sentinel);
		}
	}

	// --------------------------------------------------------------------

	public function test_image_process_imagemagick_accepts_numeric_quality()
	{
		if (file_exists($this->sentinel))
		{
			unlink($this->sentinel);
		}

		$image = $this->create_image_lib('/missing-imagemagick');
		$image->quality = '95%';

		$this->assertFalse($image->image_process_imagemagick('resize'));
		$this->assertFileDoesNotExist($this->sentinel);
	}

	// --------------------------------------------------------------------

	public function test_image_process_netpbm_escapes_dest_folder_and_rejects_invalid_rotation()
	{
		if (file_exists($this->sentinel))
		{
			unlink($this->sentinel);
		}

		$image = $this->create_image_lib('/missing-netpbm');
		$this->netpbm_prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ci_netpbm_'.getmypid().'_'.mt_rand();
		$this->netpbm_temp_path = $this->netpbm_prefix.'; touch '.$this->sentinel.'; #netpbm.tmp';
		$image->dest_folder = $this->netpbm_prefix.'; touch '.$this->sentinel.'; #';
		$image->rotation_angle = '123; touch '.$this->sentinel.'; #';
		$image->image_type = 2;

		$this->assertFalse($image->image_process_netpbm('rotate'));
		$this->assertFileDoesNotExist($this->sentinel);
	}

}

<?php

use Mockery as m;
use Illuminate\Foundation\Request;
use Illuminate\Foundation\Lightbulb;
use Illuminate\Foundation\Application;

class ApplicationTest extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass()
	{
		Lightbulb::on();
	}


	public function tearDown()
	{
		m::close();
	}


	public function testBasicRoutingIntegration()
	{
		$app = new Application;
		$app['router']->get('/foo', function() { return 'bar'; });
		$app['request'] = Request::create('/foo');
		$response = $app->dispatch($app['request']);
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
		$this->assertEquals('bar', $response->getContent());
	}


	public function testEnvironmenetDetection()
	{
		$app = new Application;
		$app['request'] = m::mock('Symfony\Component\HttpFoundation\Request');
		$app['request']->shouldReceive('getHost')->andReturn('foo');
		$app->detectEnvironment(array(
			'local'   => array('localhost')
		));
		$this->assertEquals('default', $app['env']);

		$app = new Application;
		$app['request'] = m::mock('Symfony\Component\HttpFoundation\Request');
		$app['request']->shouldReceive('getHost')->andReturn('localhost');
		$app->detectEnvironment(array(
			'local'   => array('localhost')
		));
		$this->assertEquals('local', $app['env']);

		$app = new Application;
		$app['request'] = m::mock('Symfony\Component\HttpFoundation\Request');
		$app['request']->shouldReceive('getHost')->andReturn('localhost');
		$app->detectEnvironment(array(
			'local'   => array('local*')
		));
		$this->assertEquals('local', $app['env']);

		$app = new Application;
		$app['request'] = m::mock('Symfony\Component\HttpFoundation\Request');
		$app['request']->shouldReceive('getHost')->andReturn('localhost');
		$host = gethostname();
		$app->detectEnvironment(array(
			'local'   => array($host)
		));
		$this->assertEquals('local', $app['env']);
	}

	/**
	 * @expectedException Illuminate\Session\TokenMismatchException
	 */
	public function testCsrfMiddlewareThrowsException()
	{
		$app = new Application;
		$app->register(new Illuminate\Foundation\Providers\SessionServiceProvider);
		$app['session'] = m::mock('Illuminate\Session\Store');
		$app['session']->shouldReceive('getToken')->once()->andReturn('foo');
		$app['request'] = Symfony\Component\HttpFoundation\Request::create('/', 'GET', array('csrf_token' => 'bar'));
		$middleware = $app->getFilter('csrf');
		$middleware();
	}


	public function testCsrfMiddlewareDoesntThrowWhenMatch()
	{
		$app = new Application;
		$app->register(new Illuminate\Foundation\Providers\SessionServiceProvider);
		$app['session'] = m::mock('Illuminate\Session\Store');
		$app['session']->shouldReceive('getToken')->once()->andReturn('foo');
		$app['request'] = Symfony\Component\HttpFoundation\Request::create('/', 'GET', array('csrf_token' => 'foo'));
		$middleware = $app->getFilter('csrf');
		$middleware();
		$this->assertTrue(true);
	}


	public function testPrepareRequestInjectsSession()
	{
		$app = new Application;
		$request = Illuminate\Foundation\Request::create('/', 'GET');
		$app['session'] = m::mock('Illuminate\Session\Store');
		$app->prepareRequest($request);
		$this->assertEquals($app['session'], $request->getSessionStore());
	}


	public function testExceptionHandlingSendsResponseFromCustomHandler()
	{
		$app = $this->getMock('Illuminate\Foundation\Application', array('prepareResponse'));
		$response = m::mock('stdClass');
		$response->shouldReceive('send')->once();
		$app['request'] = Request::create('/foo', 'GET');
		$app->expects($this->once())->method('prepareResponse')->with($this->equalTo('foo'), $this->equalTo($app['request']))->will($this->returnValue($response));
		$exception = new Exception;
		$errorHandler = m::mock('stdClass');
		$exceptionHandler = m::mock('stdClass');
		$exceptionHandler->shouldReceive('handle')->once()->with($exception)->andReturn('foo');
		$kernelHandler = m::mock('stdClass');
		$kernelHandler->shouldReceive('handle')->never();
		$app['kernel.exception'] = $kernelHandler;
		$app['kernel.error'] = $errorHandler;
		$app['exception'] = $exceptionHandler;
		$handler = $app['exception.function'];
		$handler($exception);
	}


	public function testNoResponseFromCustomHandlerCallsKernelExceptionHandler()
	{
		$app = new Application;
		$exception = new Exception;
		$errorHandler = m::mock('stdClass');
		$exceptionHandler = m::mock('stdClass');
		$exceptionHandler->shouldReceive('handle')->once()->with($exception)->andReturn(null);
		$kernelHandler = m::mock('stdClass');
		$kernelHandler->shouldReceive('handle')->once()->with($exception);
		$app['kernel.exception'] = $kernelHandler;
		$app['kernel.error'] = $errorHandler;
		$app['exception'] = $exceptionHandler;
		$handler = $app['exception.function'];
		$handler($exception);
	}

}

class ApplicationCustomExceptionHandlerStub extends Illuminate\Foundation\Application {

	public function prepareResponse($value, Illuminate\Foundation\Request $request)
	{
		$response = m::mock('Symfony\Component\HttpFoundation\Response');
		$response->shouldReceive('send')->once();
		return $response;
	}

	protected function setExceptionHandler(Closure $handler) { return $handler; }

}

class ApplicationKernelExceptionHandlerStub extends Illuminate\Foundation\Application {

	protected function setExceptionHandler(Closure $handler) { return $handler; }

}
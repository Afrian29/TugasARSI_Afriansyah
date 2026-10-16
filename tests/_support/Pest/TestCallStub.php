<?php

declare(strict_types=1);

namespace Pest\PendingCalls;

/**
 * phpstan stub for Pest\PendingCalls\TestCall.
 *
 * Pest binds $this inside it()/test() closure bodies to the TestCase via a proxy
 * (TestCall) that exposes assert* / get / call methods through __call. phpstan's
 * type inference does not follow that proxy, so these method declarations mirror
 * the runtime contract so that Pest test bodies can use $this->get() and
 * $this->assert*() without being flagged as undefined.
 *
 * @see Pest\PendingCalls\TestCall
 */
class TestCall
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return $this
     */
    public function __call(string $method, array $parameters): self
    {
        return $this;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return $this
     */
    public function get(string $uri, array $parameters = []): self
    {
        return $this;
    }

    /**
     * @param  string|array<string, mixed>  $uri
     * @param  array<string, mixed>  $parameters
     * @return $this
     */
    public function call(string $method, $uri, array $parameters = []): self
    {
        return $this;
    }

    /**
     * Pest proxy: throws() is invoked inside it()/test() closures and forwarded to
     * TestCase::expectException() at runtime. phpstan cannot infer the proxy, so
     * declare it here so Pest test bodies can call $this->throws(ExpectedException::class).
     *
     * @param  class-string  $class
     * @return $this
     */
    public function throws(string $class, ?string $message = null): self
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function assertStatus(int $status): self
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function assertOk(): self
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function assertSee(string $value, bool $exact = false): self
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function assertRedirect(): self
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function assertNotFound(): self
    {
        return $this;
    }
}

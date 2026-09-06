<?php

namespace App\Integrations\Sso;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * SSO Token 响应值对象。
 *
 * 授权码模式下，Laravel 后端拿到 code 后会调用公司 token 接口。
 * token 接口返回的 access_token、refresh_token、expires_in 等字段先统一整理成这个对象，
 * Controller 再把必要信息写入 Session。
 */
final readonly class SsoToken
{
    /**
     * 创建一个不可变的 SSO Token 对象。
     *
     * expiresAt 是后端计算后的过期时间，用于判断当前 Session 中的 token 是否还能继续使用。
     */
    public function __construct(
        private string $accessToken,
        private ?string $tokenType = null,
        private ?string $refreshToken = null,
        private ?int $expiresIn = null,
        private ?string $scope = null,
        private ?string $userId = null,
        private ?string $jti = null,
        private ?CarbonImmutable $expiresAt = null,
        private array $raw = [],
    ) {}

    /**
     * 从公司 token 接口完整响应中解析 access_token。
     *
     * 图片中的返回结构是：
     * {"code":"00000","data":{"access_token":"...","expires_in":35999,...},"msg":"OK"}。
     * 为了兼容少量接口差异，也允许字段直接出现在第一层。
     */
    public static function fromPayload(array $payload): self
    {
        $code = $payload['code'] ?? null;

        if (is_string($code) && $code !== '' && $code !== '00000') {
            throw new SsoException('SSO token response code is not successful.');
        }

        // 标准结构下 token 字段在 data 里；如果接口直接返回 token 字段，也允许直接解析。
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        $accessToken = $data['access_token'] ?? $data['accessToken'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new SsoException('SSO token response does not contain access token.');
        }

        $expiresIn = self::nullableInt($data['expires_in'] ?? $data['expiresIn'] ?? null);

        return new self(
            accessToken: $accessToken,
            tokenType: self::nullableString($data['token_type'] ?? $data['tokenType'] ?? null),
            refreshToken: self::nullableString($data['refresh_token'] ?? $data['refreshToken'] ?? null),
            expiresIn: $expiresIn,
            scope: self::nullableString($data['scope'] ?? null),
            userId: self::nullableString($data['user_id'] ?? $data['userId'] ?? null),
            jti: self::nullableString($data['jti'] ?? null),
            expiresAt: self::calculateExpiresAt($accessToken, $expiresIn),
            raw: $payload,
        );
    }

    /**
     * 获取 access_token。
     *
     * 该值只允许保存在后端 Session 中，并用于 Laravel 后端调用公司接口，不能暴露给 React 页面。
     */
    public function accessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * 获取 token 类型。
     *
     * 常见值是 Bearer；后续调用接口时如果需要拼 Authorization Header，可以使用该字段。
     */
    public function tokenType(): ?string
    {
        return $this->tokenType;
    }

    /**
     * 获取 refresh_token。
     *
     * 当前 MVP 暂不实现自动刷新 token，只保存原始值，后续需要时再增加刷新流程。
     */
    public function refreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * 获取 token 响应中的 expires_in 秒数。
     *
     * 该字段来自 token 接口，表示 access_token 从签发到过期的大致秒数。
     */
    public function expiresIn(): ?int
    {
        return $this->expiresIn;
    }

    /**
     * 获取 access_token 的过期时间。
     *
     * 优先从 JWT 第二段 payload 中解析 exp；解析失败时使用 expires_in 计算。
     */
    public function expiresAt(): ?CarbonImmutable
    {
        return $this->expiresAt;
    }

    /**
     * 判断 access_token 当前是否仍然有效。
     *
     * 预留 60 秒缓冲，避免 token 即将过期时刚通过检查，下一次请求马上失败。
     */
    public function isValid(): bool
    {
        if (! $this->expiresAt instanceof CarbonImmutable) {
            return $this->accessToken !== '';
        }

        return $this->expiresAt->greaterThan(now()->addSeconds(60));
    }

    /**
     * 生成写入 Laravel Session 的 token 快照。
     *
     * Session 中保存数组而不是 PHP 对象，避免类结构变化导致历史 Session 反序列化问题。
     */
    public function toSessionPayload(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'tokenType' => $this->tokenType,
            'refreshToken' => $this->refreshToken,
            'expiresIn' => $this->expiresIn,
            'scope' => $this->scope,
            'userId' => $this->userId,
            'jti' => $this->jti,
            'expiresAt' => $this->expiresAt?->toISOString(),
            'raw' => $this->raw,
        ];
    }

    /**
     * 判断 Session 中保存的 token 快照是否仍然有效。
     *
     * EnsureSsoAuthenticated 中间件会调用它，过期时重新走 SSO 登录。
     */
    public static function sessionPayloadIsValid(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $accessToken = $payload['accessToken'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            return false;
        }

        $expiresAt = $payload['expiresAt'] ?? null;

        if (! is_string($expiresAt) || $expiresAt === '') {
            return true;
        }

        try {
            return CarbonImmutable::parse($expiresAt)->greaterThan(now()->addSeconds(60));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * 计算 access_token 过期时间。
     *
     * 图片要求可以取 access_token 第二段 payload，用 Base64 解码后读取 exp 字段。
     * 如果 access_token 不是 JWT 或 exp 不存在，则回退使用 token 接口返回的 expires_in。
     */
    private static function calculateExpiresAt(string $accessToken, ?int $expiresIn): ?CarbonImmutable
    {
        if ($jwtExpiresAt = self::expiresAtFromJwt($accessToken)) {
            return $jwtExpiresAt;
        }

        if ($expiresIn !== null && $expiresIn > 0) {
            return now()->toImmutable()->addSeconds($expiresIn);
        }

        return null;
    }

    /**
     * 从 JWT 第二段 payload 中解析 exp。
     *
     * JWT 格式通常是 header.payload.signature。
     * payload 使用 Base64 URL 编码，不是普通 Base64，因此需要先替换字符并补齐等号。
     */
    private static function expiresAtFromJwt(string $accessToken): ?CarbonImmutable
    {
        $segments = explode('.', $accessToken);

        if (count($segments) < 2) {
            return null;
        }

        $payload = $segments[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode(strtr($payload, '-_', '+/'), true);

        if (! is_string($decoded)) {
            return null;
        }

        $json = json_decode($decoded, true);

        if (! is_array($json) || ! isset($json['exp']) || ! is_numeric($json['exp'])) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $json['exp']);
    }

    /**
     * 将外部接口字段安全转换为字符串或 null。
     *
     * 空字符串统一视为 null，避免 Session 中出现多种“无值”表达。
     */
    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * 将外部接口字段安全转换为整数或 null。
     *
     * expires_in 可能以数字或数字字符串返回，这里统一规整为 int。
     */
    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Secret;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Secret Server API",
 *     description="API for storing and retrieving secrets"
 * )
 */
class SecretController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/secret",
     *     summary="Store a new secret",
     *     description="Save a secret with expiration and view limits.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="secret", type="string", description="The secret text"),
     *             @OA\Property(property="expireAfterViews", type="integer", description="Number of views allowed"),
     *             @OA\Property(property="expireAfter", type="integer", description="Expiration time in minutes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Secret successfully created",
     *     )
     * )
     */
    public function addSecret(Request $request)
    {
        $validated = $this->validateRequest($request);

        $secret = Secret::create([
            'hash' => Str::random(32),
            'secret_text' => Crypt::encryptString($validated['secret']),
            'remaining_views' => $validated['expireAfterViews'],
            'expires_at' => $this->calculateExpiry($validated['expireAfter']),
        ]);

        return $this->respond($secret->toArray(), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/secret/{hash}",
     *     summary="Retrieve a secret",
     *     description="Retrieve a secret by its unique hash.",
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         required=true,
     *         description="Unique hash to identify the secret",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Secret retrieved successfully",
     *     )
     * )
     */
    public function getSecretByHash($hash)
    {
        $secret = Secret::where('hash', $hash)->first();

        if (!$secret || $this->isSecretExpired($secret)) {
            return $this->respond(['error' => 'Secret not found or expired'], 404);
        }

        $secret->decrement('remaining_views');
        $responseData = $secret->toArray();
        $responseData['secret_text'] = Crypt::decryptString($secret->secret_text);

        return $this->respond($responseData, 200);
    }

    /**
     * Validate the incoming request.
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'secret' => 'required|string',
            'expireAfterViews' => 'required|integer|min:1',
            'expireAfter' => 'required|integer|min:0',
        ]);
    }

    /**
     * Calculate the expiry time of a secret.
     */
    private function calculateExpiry(int $expireAfter): ?\DateTime
    {
        return $expireAfter > 0 ? now()->addMinutes($expireAfter) : null;
    }

    /**
     * Determine if a secret is expired.
     */
    private function isSecretExpired(Secret $secret): bool
    {
        return $secret->expires_at && now()->greaterThan($secret->expires_at);
    }

    /**
     * Respond with either JSON or XML based on the request's Accept header.
     */
    private function respond(array $data, int $status = 200)
    {
        $acceptHeader = request()->header('Accept', 'application/json');

        if (str_contains($acceptHeader, 'application/xml')) {
            return $this->respondWithXml($data, $status);
        }

        return response()->json($data, $status);
    }

    /**
     * Generate an XML response.
     */
    private function respondWithXml(array $data, int $status)
    {
        $xmlData = new \SimpleXMLElement('<root/>');
        $this->convertArrayToXml($data, $xmlData);

        return response($xmlData->asXML(), $status, ['Content-Type' => 'application/xml']);
    }

    /**
     * Recursively convert an array to XML.
     */
    private function convertArrayToXml(array $data, \SimpleXMLElement $xmlData)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subnode = $xmlData->addChild($key);
                $this->convertArrayToXml($value, $subnode);
            } else {
                $key = is_numeric($key) ? 'item' . $key : $key;
                $xmlData->addChild($key, htmlspecialchars($value));
            }
        }
    }
}

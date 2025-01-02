<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Secret;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\AuditLog;

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
     * Export all secrets in the specified format (CSV, JSON, or YAML).
     *
     * @OA\Get(
     *     path="/api/secrets/export",
     *     summary="Export all secrets",
     *     description="Export all secrets in a specified format: CSV, JSON, or YAML.",
     *     @OA\Parameter(
     *         name="format",
     *         in="query",
     *         description="The format of the export (csv, json, yaml)",
     *         required=true,
     *         @OA\Schema(type="string", enum={"csv", "json", "yaml"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Secrets exported successfully",
     *         @OA\JsonContent(type="string", example="Export data")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid format",
     *         @OA\JsonContent(type="string", example="Invalid format specified"),
     *     )
     * )
     */
    public function exportSecrets(Request $request)
    {
        $format = $request->query('format', 'json');

        if (!in_array($format, ['csv', 'json', 'yaml'])) {
            return response()->json(['error' => 'Invalid format specified'], 400);
        }

        $secrets = Secret::all()->toArray();

        switch ($format) {
            case 'csv':
                return $this->exportAsCsv($secrets);
            case 'yaml':
                return $this->exportAsYaml($secrets);
            default:
                return $this->exportAsJson($secrets);
        }
    }

    private function exportAsJson(array $secrets)
    {
        return response()->json($secrets, 200, ['Content-Type' => 'application/json']);
    }

    private function exportAsYaml(array $secrets)
    {
        $yamlContent = Yaml::dump($secrets);
        return response($yamlContent, 200, ['Content-Type' => 'application/x-yaml']);
    }

    private function exportAsCsv(array $secrets)
    {
        $output = fopen('php://temp', 'w');

        fputcsv($output, ['Hash', 'Secret Text', 'Remaining Views', 'Expires At']);

        foreach ($secrets as $secret) {
            fputcsv($output, [
                $secret['hash'],
                $secret['secret_text'],
                $secret['remaining_views'],
                $secret['expires_at'] ?? 'N/A',
            ]);
        }

        rewind($output);
        $csvData = stream_get_contents($output);
        fclose($output);

        return response($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="secrets.csv"',
        ]);
    }

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
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string", enum={"personal", "work", "confidential", "other"}), description="Optional tags for the secret")
     *          )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Secret successfully created",
     *         @OA\JsonContent(
     *             @OA\Property(property="hash", type="string", description="Unique hash for the secret"),
     *             @OA\Property(property="secret_text", type="string", description="Encrypted secret text"),
     *             @OA\Property(property="remaining_views", type="integer", description="Number of remaining views"),
     *             @OA\Property(property="expires_at", type="string", format="date-time", description="Expiration timestamp")
     *         ),
     *         @OA\XmlContent(
     *             @OA\Property(property="hash", type="string"),
     *             @OA\Property(property="secret_text", type="string"),
     *             @OA\Property(property="remaining_views", type="integer"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string", enum={"personal", "work", "confidential", "other"}), description="Optional tags for the secret")
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/x-yaml",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="hash", type="string"),
     *                 @OA\Property(property="secret_text", type="string"),
     *                 @OA\Property(property="remaining_views", type="integer"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string", enum={"personal", "work", "confidential", "other"}), description="Optional tags for the secret")
     *             )
     *         )
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
            'tags' => $validated['tags'] ?? null,
        ]);

        AuditLog::create([
            'secret_id' => $secret->id,
            'action' => 'created',
            'timestamp' => now(),
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
     *         @OA\JsonContent(
     *             @OA\Property(property="hash", type="string"),
     *             @OA\Property(property="secret_text", type="string"),
     *             @OA\Property(property="remaining_views", type="integer"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *               @OA\Property(property="tags", type="array", @OA\Items(type="string", enum={"personal", "work", "confidential", "other"}), description="Optional tags for the secret")
     *         ),
     *         @OA\XmlContent(
     *             @OA\Property(property="hash", type="string"),
     *             @OA\Property(property="secret_text", type="string"),
     *             @OA\Property(property="remaining_views", type="integer"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *               @OA\Property(property="tags", type="array", @OA\Items(type="string", enum={"personal", "work", "confidential", "other"}), description="Optional tags for the secret")
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/x-yaml",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="hash", type="string"),
     *                 @OA\Property(property="secret_text", type="string"),
     *                 @OA\Property(property="remaining_views", type="integer"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *                   @OA\Property(property="tags", type="array", @OA\Items(type="string", enum={"personal", "work", "confidential", "other"}), description="Optional tags for the secret")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Secret not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Secret not found or expired")
     *         ),
     *         @OA\XmlContent(
     *             @OA\Property(property="error", type="string", example="Secret not found or expired")
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/x-yaml",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Secret not found or expired")
     *             )
     *         )
     *     )
     * )
     */
    public function getSecretByHash($hash)
    {
        $secret = Secret::where('hash', $hash)->first();

        if (!$secret || $this->isSecretExpired($secret) || $secret->remaining_views <= 0) {
            return $this->respond(['error' => 'Secret not found or expired'], 404);
        }

        $secret->decrement('remaining_views');

        AuditLog::create([
            'secret_id' => $secret->id,
            'action' => 'accessed',
            'timestamp' => now(),
        ]);

        $responseData = $secret->toArray();
        $responseData['tags'] = $secret->tags;
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
            'expireAfterViews' => 'required|integer|min:1|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'in:personal,work,other'
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
     * Respond with either JSON or XML or YAML based on the request's Accept header.
     */
    private function respond(array $data, int $status = 200)
    {
        $acceptHeader = request()->header('Accept', 'application/json');

        if (str_contains($acceptHeader, 'application/xml')) {
            return $this->respondWithXml($data, $status);
        }
        
        if (str_contains($acceptHeader, 'application/x-yaml')){
            return $this->respondWithYaml($data, $status);
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

    /**
     * Convert content to YAML.
     */
    private function respondWithYaml(array $data, int $status)
    {
        $yamlContent = Yaml::dump($data);

        return response($yamlContent, $status, ['Content-Type' => 'application/x-yaml']);
    }

    public function getPaginatedSecrets(Request $request)
    {
        $query = Secret::query();

        if ($request->has('expires_soon')) {
            $query->where('expires_at', '>=', now())->where('expires_at', '<=', now()->addMinutes(30));
        }

        if ($request->has('low_views')) {
            $query->where('remaining_views', '<', 5);
        }

        $secrets = $query->paginate(
            $request->get('per_page', 10)
        );

        return response()->json($secrets);
    }

    public function searchSecrets(Request $request)
    {
        $query = Secret::query();

        if ($request->has('created_from') && $request->has('created_to')) {
            $query->whereBetween('created_at', [
                $request->input('created_from'),
                $request->input('created_to'),
            ]);
        }

        if ($request->has('expires_from') && $request->has('expires_to')) {
            $query->whereBetween('expires_at', [
                $request->input('expires_from'),
                $request->input('expires_to'),
            ]);
        }

        if ($request->has('remaining_views')) {
            $query->where('remaining_views', '<=', $request->input('remaining_views'));
        }

        $results = $query->paginate($request->get('per_page', 10));

        return response()->json($results);
    }

    public function getAuditLogs($id)
    {
        $logs = AuditLog::where('secret_id', $id)->get();

        return response()->json($logs);
    }

}

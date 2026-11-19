<?php

namespace AwsStorage\Traits;

trait AwsPolicyTrait
{
    /**
     * Generates User Policy Array
     *
     * @param  string $bucketName
     * @return array
     */
    protected function getUserPolicy(string $bucketName)
    {
        return [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'MediaBucketAccess',
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:ListBucket',
                        's3:GetBucketLocation',
                    ],
                    'Resource' => "arn:aws:s3:::{$bucketName}",
                ],
                [
                    'Sid' => 'MediaObjectAccess',
                    'Effect' => 'Allow',
                    'Action' => [
                        's3:GetObject',
                        's3:PutObject',
                        's3:DeleteObject',
                        's3:AbortMultipartUpload',
                        's3:ListMultipartUploadParts',
                    ],
                    'Resource' => "arn:aws:s3:::{$bucketName}/*",
                ],
            ],
        ];
    }

    /**
     * Generates Bucket Policy
     *
     * @param  string  $bucketName
     * @param  string  $bucketUsername
     * @param  ?string $cloudfrontArn
     * @return array
     */
    protected function getBucketPolicy(string $accountId, string $bucketName, string $bucketUsername, ?string $cloudfrontArn = null)
    {
        $policy = [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Sid' => 'AllowBucketItemListing',
                    'Effect' => 'Allow',
                    'Principal' => [
                        'AWS' => "arn:aws:iam::{$accountId}:user/s3-storage/{$bucketUsername}",
                    ],
                    'Action' => 's3:ListBucket',
                    'Resource' => "arn:aws:s3:::{$bucketName}",
                ],
                [
                    'Sid' => 'AllowFileManagement',
                    'Effect' => 'Allow',
                    'Principal' => [
                        'AWS' => "arn:aws:iam::{$accountId}:user/s3-storage/{$bucketUsername}",
                    ],
                    'Action' => [
                        's3:GetObject',
                        's3:PutObject',
                        's3:DeleteObject',
                    ],
                    'Resource' => "arn:aws:s3:::{$bucketName}/*",
                ]
            ]
        ];

        if ($cloudfrontArn !== null) {
            $policy['Statement'][] = [
                'Sid' => 'AllowCloudFrontAccess',
                'Effect' => 'Allow',
                'Principal' => [
                    'Service' => 'cloudfront.amazonaws.com',
                ],
                'Action' => 's3:GetObject',
                'Resource' => "arn:aws:s3:::{$bucketName}/*",
                'Condition' => [
                    'StringEquals' => [
                        'AWS:SourceArn' => "{$cloudfrontArn}",
                    ],
                ],
            ];
        }

        return $policy;
    }

    /**
     * Generate Cloudfront Access Control Policy
     *
     * @param  string $bucketName
     * @param  string $bucketUsername
     * @return array
     */
    protected function getCloudfrontAccessControlPolicy(string $bucketName, string $bucketUsername)
    {
        return [
            'OriginAccessControlConfig' => [
                'Name' => $bucketUsername . '-oac',
                'Description' => 'Access control for ' . $bucketName . ' bucket',
                'SigningProtocol' => 'sigv4',
                'SigningBehavior' => 'always',
                'OriginAccessControlOriginType' => 's3',
            ],
        ];
    }
}

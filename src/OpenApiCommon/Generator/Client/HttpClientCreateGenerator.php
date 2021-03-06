<?php

namespace Jane\OpenApiCommon\Generator\Client;

use Http\Client\Common\PluginClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Jane\JsonSchema\Generator\Context\Context;
use Jane\OpenApi3\JsonSchema\Model\OpenApi;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node;

trait HttpClientCreateGenerator
{
    abstract protected function getPsr18ClientGeneratorClass(): string;

    protected function getHttpClientCreateExpr(Context $context): array
    {
        $psr18ClientGeneratorClass = $this->getPsr18ClientGeneratorClass();
        $discoveryClientClass = $this instanceof $psr18ClientGeneratorClass ? Psr18ClientDiscovery::class : HttpClientDiscovery::class;

        /** @var OpenApi $openApi */
        $openApi = $context->getCurrentSchema()->getParsed();
        $statements = [
            new Stmt\Expression(new Expr\Assign(
                new Expr\Variable('httpClient'),
                new Expr\StaticCall(
                    new Name\FullyQualified($discoveryClientClass),
                    'find'
                )
            )),
        ];

        $needsServerPlugins = $this->needsServerPlugins($openApi);

        $statements[] = new Stmt\Expression(new Expr\Assign(
            new Expr\Variable('plugins'),
            new Expr\Array_()
        ));

        if ($needsServerPlugins) {
            $statements = array_merge($statements, $this->getServerPluginsStatements($openApi));
        }

        $statements[] = new Stmt\If_(
            new Expr\BinaryOp\Greater(
                new Expr\FuncCall(new Name('count'), [new Node\Arg(new Expr\Variable('additionalPlugins'))]),
                new Expr\ConstFetch(new Name('0'))
            ),
            [
                'stmts' => [
                    new Stmt\Expression(new Expr\Assign(
                        new Expr\Variable('plugins'),
                        new Expr\FuncCall(new Name('array_merge'), [
                            new Node\Arg(new Expr\Variable('plugins')),
                            new Node\Arg(new Expr\Variable('additionalPlugins')),
                        ])
                    )),
                ],
            ]
        );

        $statements[] = new Stmt\Expression(new Expr\Assign(
            new Expr\Variable('httpClient'),
            new Expr\New_(
                new Name\FullyQualified(PluginClient::class),
                [
                    new Node\Arg(new Expr\Variable('httpClient')),
                    new Node\Arg(new Expr\Variable('plugins')),
                ]
            )
        ));

        return $statements;
    }
}

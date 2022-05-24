<?php

require_once 'modules/admin/models/ServerPlugin.php';
require_once 'plugins/server/apiscp/ApisCPAPI.php';

class PluginApiscp extends ServerPlugin
{
    public $features = [
        'packageName' => true,
        'testConnection' => true,
        'showNameservers' => false,
        'directlink' => true
    ];

    public function getVariables()
    {
        $variables = [
            'Name' => [
                'type' => 'hidden',
                'description' => 'Used by CE to show plugin',
                'value' => 'ApisCP'
            ],
            'API Key' => [
                'type' => 'text',
                'description' => 'ApisCP API Key',
                'value' => '',
                'encryptable' => true
            ],
            'Port' => [
                "type" => 'text',
                "description" => 'Port',
                "value" => 2083
            ],
            'Actions' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin per server',
                'value'=>'Create,Delete,Suspend,UnSuspend'
            ],
            'Registered Actions For Customer' => [
                'type' => 'hidden',
                'description' => 'Current actions that are active for this plugin per server for customers',
                'value' => ''
            ],
            'package_addons' => [
                'type' => 'hidden',
                'description' => 'Supported signup addons variables',
                'value' => ''
            ],
            'package_vars' => [
                'type' => 'hidden',
                'description' => 'Whether package settings are set',
                'value' => '0',
            ],
            'package_vars_values' => []
        ];

        return $variables;
    }

    public function validateCredentials($args)
    {
        return trim(strtolower($args['package']['username']));
    }

    public function doDelete($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->delete($args);
        return 'Package has been deleted.';
    }

    public function doCreate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->create($args);
        return 'Package has been created.';
    }

    public function doSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->suspend($args);
        return 'Package has been suspended.';
    }

    public function doUnSuspend($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $args = $this->buildParams($userPackage);
        $this->unsuspend($args);
        return 'Package has been unsuspended.';
    }

    public function doUpdate($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $this->update($this->buildParams($userPackage, $args));
        return $userPackage->getCustomField("Domain Name") . ' has been updated.';
    }

    public function update($args)
    {
        $userPackage = new UserPackage($args['package']['id']);
        $client = $this->getClient($args);

        foreach ($args['changes'] as $key => $value) {
            switch ($key) {
                case 'password':
                    try {
                        $client->auth_change_password(
                            $value,
                            $args['package']['username'],
                            strtolower($userPackage->getCustomField('Domain Name'))
                        );
                    } catch (Exception $e) {
                        throw new CE_Exception($e->getMessage());
                    }
                    break;
                case 'package':
                    $options['siteinfo.plan'] = $args['package']['name_on_server'];
                    $extra['reset'] = 'true';

                    try {
                        $client->admin_edit_site(strtolower($userPackage->getCustomField('Domain Name')), $options, $extra);
                    } catch (Exception $e) {
                        throw new CE_Exception($e->getMessage());
                    }
                    break;
            }
        }
    }

    public function unsuspend($args)
    {
        $client = $this->getClient($args);
        try {
            $client->admin_activate_site(strtolower($args['package']['domain_name']));
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }
    }

    public function suspend($args)
    {
        $client = $this->getClient($args);
        try {
            $client->admin_deactivate_site(strtolower($args['package']['domain_name']));
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }
    }

    public function delete($args)
    {
        $client = $this->getClient($args);
        try {
            $client->admin_delete_site(strtolower($args['package']['domain_name']));
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }
    }

    public function getAvailableActions($userPackage)
    {
        $args = $this->buildParams($userPackage);
        $client = $this->getClient($args);

        $actions = [];
        try {
            $response = $client->admin_collect(['siteinfo'], null, [strtolower($userPackage->getCustomField('Domain Name'))]);
            if (count($response) == 0) {
                $actions[] = 'Create';
                return $actions;
            }
            if ($response[strtolower($userPackage->getCustomField('Domain Name'))]['active'] == 1) {
                $actions[] = 'Suspend';
            } else {
                $actions[] = 'UnSuspend';
            }
            $actions[] = 'Delete';
        } catch (Exception $e) {
            $actions[] = 'Create';
        }
        return $actions;
    }

    public function create($args)
    {
        $options = [
            'siteinfo.enabled' => 1,
            'siteinfo.domain' => strtolower($args['package']['domain_name']),
            'siteinfo.admin_user' => strtolower($args['package']['username']),
            'siteinfo.email' => $args['customer']['email'],
            'siteinfo.plan' => $args['package']['name_on_server'],
            'auth.tpasswd' => $args['package']['password']
        ];

        $client = $this->getClient($args);
        try {
            $client->admin_add_site(
                $args['package']['domain_name'],
                $args['package']['username'],
                $options
            );
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }
    }

    public function testConnection($args)
    {
        CE_Lib::log(4, 'Testing connection to ApisCP server');
        $client = $this->getClient($args);

        try {
            $client->common_whoami();
        } catch (Exception $e) {
            throw new CE_Exception($e->getMessage());
        }
    }

    private function getClient($args)
    {
        $client = ApisCPAPI::create_client(
            $args['server']['variables']['ServerHostName'],
            $args['server']['variables']['plugin_apiscp_Port'],
            $args['server']['variables']['plugin_apiscp_API_Key'],
            session_id()
        );
        return $client;
    }


    public function getDirectLink($userPackage, $getRealLink = true, $fromAdmin = false)
    {
        $linkText = $this->user->lang('Login to Server');
        $args = $this->buildParams($userPackage);
        $client = $this->getClient($args);

        $sessionId = $client->admin_hijack(
            $args['package']['domain_name'],
            $args['package']['username'],
            'UI'
        );

        $query['esprit_id'] = $sessionId;
        $query = http_build_query($query);
        $url = "https://{$args['server']['variables']['ServerHostName']}:{$args['server']['variables']['plugin_apiscp_Port']}/apps/dashboard?{$query}";

        if ($fromAdmin) {
            return [
                'cmd' => 'panellogin',
                'label' => $linkText
            ];
        } elseif ($getRealLink) {
            return [
                'link'    => '<li><a target="_blank" href="' . $url . '">' .$linkText . '</a></li>',
                'rawlink' => $url,
                'form'    => ''
            ];
        } else {
            return [
                'link' => '<li><a target="_blank" href="index.php?fuse=clients&controller=products&action=openpackagedirectlink&packageId='.$userPackage->getId().'&sessionHash='.CE_Lib::getSessionHash().'">' .$linkText . '</a></li>',
                'form' => ''
            ];
        }
    }

    public function dopanellogin($args)
    {
        $userPackage = new UserPackage($args['userPackageId']);
        $response = $this->getDirectLink($userPackage, true);
        return $response['rawlink'];
    }
}

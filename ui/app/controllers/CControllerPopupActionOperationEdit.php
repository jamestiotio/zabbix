<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerPopupActionOperationEdit extends CController {

	protected function checkInput(): bool {
		$fields = [
			'eventsource' =>	'required|in '.implode(',', [
									EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
									EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
								]),
			'recovery' =>		'required|in '.implode(',', [
									ACTION_OPERATION, ACTION_RECOVERY_OPERATION, ACTION_UPDATE_OPERATION
								]),
			'actionid' =>		'db actions.actionid',
			'operation' =>		'array',
			'operationid' =>	'string',
			'data' =>			'array',
			'operationtype' =>	'int32'
		];

		$ret = $this->validateInput($fields) && $this->validateInputConstraints();

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])])
			);
		}

		return $ret;
	}

	protected function validateInputConstraints(): bool {
		$eventsource = $this->getInput('eventsource');
		$recovery = $this->getInput('recovery');
		$allowed_operations = getAllowedOperations($eventsource);

		if (!array_key_exists($recovery, $allowed_operations)) {
			error(_('Unsupported operation.'));
			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN) {
			if (!$this->getInput('actionid', '0')) {
				return true;
			}

			return (bool) API::Action()->get([
				'output' => [],
				'actionids' => $this->getInput('actionid'),
				'editable' => true
			]);
		}

		return false;
	}

	protected function doAction(): void {
		$operation = $this->getInput('data', []) + $this->defaultOperationObject();
		$eventsource = (int) $this->getInput('eventsource');
		$recovery = (int) $this->getInput('recovery');
		$operation_types = $this->popupConfigOperationTypes($operation, $eventsource, $recovery)['options'];

		foreach ($operation_types as $type) {
			$operation_type[$type['value']] = $type['name'];
		}

		$media_types = $this->popupConfigOperationMessage($operation, $eventsource)['mediatypes'];
		$media_type[] = '- '._('All').' -';
		$disabled_media = [];

		foreach($media_types as $type) {
			$media_type[$type['mediatypeid']] = $type['name'];
			if ($type['status'] == MEDIA_TYPE_STATUS_DISABLED) {
				$disabled_media[] = $type['mediatypeid'];
			}
		}

		$this->getData($operation);

		$data = [
			'eventsource' => $eventsource,
			'actionid' => $this->getInput('actionid', []),
			'recovery' => $recovery,
			'operation' => $operation,
			'operation_types' => $operation_type,
			'mediatype_options' => CSelect::createOptionsFromArray($media_type),
			'disabled_media' => $disabled_media
		];
		$this->setResponse(new CControllerResponseData($data));
	}

	private function getData(&$operation) {
		$result = [];

		if (array_key_exists('0', $operation['opcommand_hst'])) {
			if ($operation['opcommand_hst'][0]['hostid'] == 0) {
				$host = '';
				$result[] = $host;
			}
			else if ($operation['opcommand_hst'][0]['hostid'] !== '0') {
				foreach($operation['opcommand_hst'] as &$host) {
					$host = API::Host()->get([
						'output' => ['hostid', 'name'],
						'hostids' => $host['hostid']
					]);
				}
				$result[] = $host;
			}
		}

		if ($operation['opcommand_grp']) {
			foreach($operation['opcommand_grp'] as &$host_group) {
				$host_group = API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $host_group['groupid']
				]);
			}
			$result[] = $host_group;
		}

		if($operation['opgroup']) {
			foreach($operation['opgroup'] as &$host_group) {
				$host_group = API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'groupids' => $host_group['groupid']
				]);
			}
			$result[] = $host_group;
		}

		if($operation['optemplate']) {
			foreach($operation['optemplate'] as &$template) {
				$template = API::Template()->get([
					'output' => ['name'],
					'templateids' => $template['templateid']
				]);
			}
			$result[] = $template;
		}

		if ($operation['opmessage_grp']) {
			$i = 0;

			foreach ($operation['opmessage_grp'] as $opmessage_grp) {
				$usr_grpids = $opmessage_grp['usrgrpid'];

				$user_groups = API::UserGroup()->get([
					'output' => ['name'],
					'usrgrpids' => $usr_grpids,
					'preservekeys' => true
				]);

				foreach ($user_groups as $user_group) {
					$operation['opmessage_grp'][$i]['name'] = $user_group['name'];
					$i++;
				}
			}
			$result['user_group'] = $user_group;
		}

		if ($operation['opmessage_usr']) {
			$i = 0;
			foreach ($operation['opmessage_usr'] as $opmessage_usr) {
				$userids = $opmessage_usr['userid'];

				$fullnames = [];

				$users = API::User()->get([
					'output' => ['userid', 'username', 'name', 'surname'],
					'userids' => $userids,
					'preservekeys' => true
				]);

				foreach ($users as $user) {
					$fullnames[$user['userid']] = getUserFullname($user);

					$operation['opmessage_usr'][$i]['name'] = $fullnames[$opmessage_usr['userid']];
					$i++;
				}
			}
			$result['users'] = $users;
		}

		return $result;
	}

	private function popupConfigOperationMessage(array $operation): array {
		$usergroups = [];
		if ($operation['opmessage_grp']) {
			$usergroups = API::UserGroup()->get([
				'output' => ['usergroupid', 'name'],
				'usrgrpids' => array_column($operation['opmessage_grp'], 'usrgrpid')
			]);
		}

		$users = [];
		if ($operation['opmessage_usr']) {
			$db_users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_column($operation['opmessage_usr'], 'userid')
			]);
			CArrayHelper::sort($db_users, ['username']);

			foreach ($db_users as $db_user) {
				$users[] = [
					'userid' => $db_user['userid'],
					'name' => getUserFullname($db_user)
				];
			}
		}

		$mediatypes = API::MediaType()->get(['output' => ['mediatypeid', 'name', 'status']]);
		CArrayHelper::sort($mediatypes, ['name']);
		$mediatypes = array_values($mediatypes);

		return [
			'custom_message' => ($operation['opmessage']['default_msg'] === '1'),
			'subject' => array_key_exists('subject', $operation['opmessage']) ? $operation['opmessage']['subject'] : '',
			'body' =>array_key_exists('message', $operation['opmessage']) ? $operation['opmessage']['message'] : '',
			'mediatypeid' => $operation['opmessage']['mediatypeid'],
			'mediatypes' => $mediatypes,
			'usergroups' => $usergroups,
			'users' => $users
		];
	}

	private function defaultOperationObject(): array {
		return [
			'opmessage_usr' => [],
			'opmessage_grp' => [],
			'opmessage' => [
				'subject' => '',
				'message' => '',
				'mediatypeid' => '0',
				'default_msg' => '1'
			],
			'operationtype' => '0',
			'esc_step_from' => '1',
			'esc_step_to' => '1',
			'esc_period' => '0',
			'opcommand_hst' => [],
			'opcommand_grp' => [],
			'evaltype' => (string) CONDITION_EVAL_TYPE_AND_OR,
			'opconditions' => [],
			'opgroup' => [],
			'optemplate' => [],
			'opinventory' => [
				'inventory_mode' => (string) HOST_INVENTORY_MANUAL
			],
			'opcommand' => [
				'scriptid' => '0'
			]
		];
	}

	/**
	 * Returns "operation type" configuration fields for given operation in given source.
	 *
	 * @param array $operation  Operation object.
	 * @param int $eventsource  Action event source.
	 * @param int $recovery     Action event phase.
	 *
	 * @return array
	 */
	private function popupConfigOperationTypes(array $operation, int $eventsource, int $recovery): array {
		$operation_type_options = [];
		$scripts_allowed = false;

		// First determine if scripts are allowed for this action type.
		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			if ($operation_type == OPERATION_TYPE_COMMAND) {
				$scripts_allowed = true;

				break;
			}
		}

		// Then remove Remote command from dropdown list.
		foreach (getAllowedOperations($eventsource)[$recovery] as $operation_type) {
			if ($operation_type == OPERATION_TYPE_COMMAND) {
				continue;
			}

			$operation_type_options[] = [
				'value' => 'cmd['.$operation_type.']',
				'name' => operation_type2str($operation_type)
			];
		}

		if ($scripts_allowed) {
			$db_scripts = API::Script()->get([
				'output' => ['name', 'scriptid'],
				'filter' => ['scope' => ZBX_SCRIPT_SCOPE_ACTION],
				'sortfield' => 'name',
				'sortorder' => ZBX_SORT_UP
			]);

			if ($db_scripts) {
				foreach ($db_scripts as $db_script) {
					$operation_type_options[] = [
						'value' => 'scriptid['.$db_script['scriptid'].']',
						'name' => $db_script['name']
					];
				}
			}
		}

		return [
			'options' => $operation_type_options,
			'selected' => ($operation['opcommand']['scriptid'] == 0)
				? 'cmd['.$operation['operationtype'].']'
				: 'scriptid['.$operation['opcommand']['scriptid'].']'
		];
	}
}

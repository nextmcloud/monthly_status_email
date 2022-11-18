<template>
	<div id="monthly-notifications-settings" class="section">
		<h1>{{ t('monthly_status_email', 'Regular mails') }}</h1>
		<p>
			<input id="send-notifications"
				v-model="sendNotifications"
				type="checkbox"
				class="checkbox">
			<label for="send-notifications">{{ t('monthly_status_email', 'Monthly Status Report') }}</label>
		</p>
		<p class="settings-hint">
			{{ t('monthly_status_email', 'The status report informs you monthly by email about storage space, your shares and gives you useful tips about the MagentaCLOUD.') }}
		</p>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'PersonalSettings',
	data() {
		return {
			sendNotifications: !loadState('monthly_status_email', 'opted-out', false),
		}
	},
	watch: {
		sendNotifications() {
			this.saveSetting()
		},
	},
	methods: {
		async saveSetting() {
			const data = {
				optedOut: !this.sendNotifications,
			}
			await axios.post(generateUrl('/apps/monthly_status_email/') + 'update', data)
		},
	},
}
</script>

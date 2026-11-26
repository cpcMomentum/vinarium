/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare module 'vue-cropperjs' {
	import type { DefineComponent } from 'vue'
	import type Cropper from 'cropperjs'

	type CropperMethods = Pick<Cropper, 'getCroppedCanvas'>
	const VueCropper: DefineComponent<object, object, object, object, CropperMethods>
	export default VueCropper
}

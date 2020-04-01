<template>
    <div>
        <div class="m-2 rounded-sm overflow-hidden border border-grey-lighter relative bg-white">
            <img :src="'/image-factory/636x636:contain/' + image.file" :alt="image.alt" class="block">
            <div class="absolute pin hover:opacity-100" :class="{ 'opacity-0': !showing }">
                <div class="absolute pin cursor-move handler"></div>
                <slot :value="value" :image="image" :toggle-show="toggleShow" :update-image="updateImage"></slot>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            value: {type: String, required: true},
        },
        data() {
            return {
                showing: false,
            }
        },
        computed: {
            image() {
                return JSON.parse(this.value);
            },
        },
        methods: {
            toggleShow() {
                this.showing = !this.showing;
            },
            updateImage() {
                this.$emit('updated', JSON.stringify(this.image));
            }
        },
    }
</script>

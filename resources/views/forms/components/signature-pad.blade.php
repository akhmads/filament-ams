<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            drawing: false,
            ctx: null,
            init() {
                const canvas = this.$refs.canvas;
                const ratio = window.devicePixelRatio || 1;

                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = {{ $getCanvasHeight() }} * ratio;
                canvas.style.height = '{{ $getCanvasHeight() }}px';

                this.ctx = canvas.getContext('2d');
                this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#111827';

                if (this.state) {
                    const image = new Image();
                    image.onload = () => this.ctx.drawImage(image, 0, 0, canvas.offsetWidth, {{ $getCanvasHeight() }});
                    image.src = this.state;
                }
            },
            position(event) {
                const rect = this.$refs.canvas.getBoundingClientRect();
                const source = event.touches ? event.touches[0] : event;

                return { x: source.clientX - rect.left, y: source.clientY - rect.top };
            },
            start(event) {
                event.preventDefault();
                this.drawing = true;
                const { x, y } = this.position(event);
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
            },
            move(event) {
                if (! this.drawing) return;
                event.preventDefault();
                const { x, y } = this.position(event);
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
            },
            stop() {
                if (! this.drawing) return;
                this.drawing = false;
                this.state = this.$refs.canvas.toDataURL('image/png');
            },
            clear() {
                this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                this.state = null;
            },
        }"
        class="space-y-2"
    >
        <canvas
            x-ref="canvas"
            class="w-full cursor-crosshair rounded-lg border border-gray-300 bg-white dark:border-gray-600"
            @mousedown="start($event)"
            @mousemove="move($event)"
            @mouseup="stop()"
            @mouseleave="stop()"
            @touchstart="start($event)"
            @touchmove="move($event)"
            @touchend="stop()"
        ></canvas>

        <button
            type="button"
            @click="clear()"
            class="text-sm text-gray-500 underline hover:text-gray-700 dark:text-gray-400"
        >
            Hapus tanda tangan
        </button>
    </div>
</x-dynamic-component>

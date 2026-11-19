const svgNamespace = 'http://www.w3.org/2000/svg';

export function renderLoadFloor(container, state, colors) {
    const svg = document.createElementNS(svgNamespace, 'svg');
    svg.setAttribute('viewBox', `0 0 ${state.length} ${state.width}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Top view of the first cargo layer');
    svg.classList.add('load-floor-svg');
    for (const row of state.placements) {
        if (row.z > 1e-7) continue;
        const rect = document.createElementNS(svgNamespace, 'rect');
        rect.setAttribute('x', row.x);
        rect.setAttribute('y', row.y);
        rect.setAttribute('width', row.l * row.count);
        rect.setAttribute('height', row.w);
        rect.setAttribute('fill', colors.get(row.boxId));
        rect.setAttribute('stroke', '#fff');
        rect.setAttribute('stroke-width', '1.5');
        svg.append(rect);
    }
    container.replaceChildren(svg);
}

const vertexShader = `
    attribute vec3 point;
    attribute vec3 color;
    varying vec3 faceColor;
    uniform vec3 rightAxis, upAxis, depthAxis, center, scale;
    void main() {
        vec3 p = point - center;
        gl_Position = vec4(vec3(dot(p, rightAxis), dot(p, upAxis), dot(p, depthAxis)) * scale, 1.0);
        faceColor = color;
    }
`;
const fragmentShader = `
    precision mediump float;
    varying vec3 faceColor;
    void main() { gl_FragColor = vec4(faceColor, 1.0); }
`;
const faces = [
    [0, 1, 2, 3, .68], [4, 5, 6, 7, 1], [0, 1, 5, 4, .84],
    [1, 2, 6, 5, .72], [2, 3, 7, 6, .84], [3, 0, 4, 7, .72],
];
const edges = [
    [0, 1], [1, 2], [2, 3], [3, 0], [4, 5], [5, 6], [6, 7], [7, 4],
    [0, 4], [1, 5], [2, 6], [3, 7],
];

function corners(x, y, z, length, width, height) {
    const X = x + length;
    const Y = y + width;
    const Z = z + height;
    return [[x,y,z], [X,y,z], [X,Y,z], [x,Y,z], [x,y,Z], [X,y,Z], [X,Y,Z], [x,Y,Z]];
}

function appendLine(buffer, from, to, color, dashed = false) {
    if (!dashed) {
        buffer.push(...from, ...color, ...to, ...color);
        return;
    }
    const steps = Math.min(18, Math.max(3, Math.round(Math.hypot(...to.map((v, i) => v - from[i])) / 7)));
    for (let i = 0; i < steps; i += 2) {
        const start = from.map((v, axis) => v + (to[axis] - v) * i / steps);
        const end = from.map((v, axis) => v + (to[axis] - v) * (i + 1) / steps);
        buffer.push(...start, ...color, ...end, ...color);
    }
}

function appendCuboid(surfaces, lines, points, color, outline, dashed) {
    for (const [a,b,c,d,light] of faces) {
        const shade = color.map(component => component * light);
        for (const i of [a,b,c,a,c,d]) surfaces.push(...points[i], ...shade);
    }
    for (const [a,b] of edges) appendLine(lines, points[a], points[b], outline, dashed);
}

function compile(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        gl.deleteShader(shader);
        throw new Error('Could not initialize 3D drawing.');
    }
    return shader;
}

export function createLoadView(canvas, cabLabel, rearLabel) {
    const gl = canvas.getContext('webgl', {antialias: true});
    if (!gl) return null;
    const program = gl.createProgram();
    const vertex = compile(gl, gl.VERTEX_SHADER, vertexShader);
    const fragment = compile(gl, gl.FRAGMENT_SHADER, fragmentShader);
    gl.attachShader(program, vertex);
    gl.attachShader(program, fragment);
    gl.linkProgram(program);
    gl.deleteShader(vertex);
    gl.deleteShader(fragment);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return null;
    gl.useProgram(program);
    const positionLocation = gl.getAttribLocation(program, 'point');
    const colorLocation = gl.getAttribLocation(program, 'color');
    const uniforms = Object.fromEntries(['rightAxis', 'upAxis', 'depthAxis', 'center', 'scale']
        .map(name => [name, gl.getUniformLocation(program, name)]));
    const layers = [gl.TRIANGLES, gl.LINES, gl.LINES].map(mode => ({mode, buffer: gl.createBuffer(), count: 0}));
    gl.enableVertexAttribArray(positionLocation);
    gl.enableVertexAttribArray(colorLocation);
    gl.enable(gl.DEPTH_TEST);
    gl.depthFunc(gl.LEQUAL);
    gl.clearColor(.949, .965, .969, 1);
    gl.polygonOffset(1, 1);

    let truck = null;
    let yaw = 30;
    let elevation = 28;

    function draw() {
        if (!truck || !canvas.clientWidth || gl.isContextLost()) return;
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        const width = Math.round(canvas.clientWidth * ratio);
        const height = Math.round(canvas.clientHeight * ratio);
        if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
        }
        const angle = yaw * Math.PI / 180;
        const tilt = elevation * Math.PI / 180;
        const c = Math.cos(angle);
        const s = Math.sin(angle);
        const h = Math.sin(tilt);
        const v = Math.cos(tilt);
        const right = [c, -s, 0];
        const up = [s * h, c * h, v];
        const depth = [s * v, c * v, -h];
        const dimensions = [truck.length, truck.width, truck.height];
        const center = dimensions.map(value => value / 2);
        const extent = axis => axis.reduce((sum, factor, i) => sum + Math.abs(factor) * dimensions[i], 0);
        const pixelsPerCm = Math.min(.83 * width / extent(right), .76 * height / extent(up));
        const scale = [2 * pixelsPerCm / width, 2 * pixelsPerCm / height, 1.9 / extent(depth)];
        gl.viewport(0, 0, width, height);
        gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
        for (const [name, values] of Object.entries({rightAxis: right, upAxis: up, depthAxis: depth, center, scale})) {
            gl.uniform3fv(uniforms[name], values);
        }
        for (const {mode, buffer, count} of layers) {
            if (mode === gl.TRIANGLES) gl.enable(gl.POLYGON_OFFSET_FILL);
            else gl.disable(gl.POLYGON_OFFSET_FILL);
            gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
            gl.vertexAttribPointer(positionLocation, 3, gl.FLOAT, false, 24, 0);
            gl.vertexAttribPointer(colorLocation, 3, gl.FLOAT, false, 24, 12);
            gl.drawArrays(mode, 0, count);
        }
        for (const [label, x] of [[cabLabel, 0], [rearLabel, truck.length]]) {
            const y = truck.width / 2;
            label.style.left = `${50 + 50 * (right[0] * (x - center[0]) + right[1] * (y - center[1])) * scale[0]}%`;
            label.style.top = `${50 - 50 * (up[0] * (x - center[0]) + up[1] * (y - center[1]) - up[2] * center[2]) * scale[1]}%`;
            label.hidden = false;
        }
    }

    const observer = new ResizeObserver(draw);
    observer.observe(canvas);

    function setLoad(state, visible, colors, packages, selectedRow) {
        truck = state;
        const wire = [];
        const surfaces = [];
        const outlines = [];
        const body = corners(0, 0, 0, truck.length, truck.width, truck.height);
        for (const [a,b] of edges) appendLine(wire, body[a], body[b], [.39,.50,.55], a >= 4 || b >= 4);
        const groupRows = visible.reduce((sum, row) => sum + row.count, 0) > 1500;
        for (const row of visible) {
            const hex = colors.get(row.boxId);
            const color = [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16) / 255);
            const irregular = packages.get(row.boxId).shape === 'Irregular';
            const outline = row === selectedRow ? [1,.95,.44] : irregular ? [.25,.16,.07] : [.13,.23,.27];
            const count = groupRows ? 1 : row.count;
            const length = row.l * row.count / count;
            for (let i = 0; i < count; i++) {
                const points = corners(row.x + i * length, row.y, row.z, length, row.w, row.h);
                appendCuboid(surfaces, outlines, points, color, outline, irregular);
            }
        }
        for (const [i, values] of [surfaces, wire, outlines].entries()) {
            const layer = layers[i];
            layer.count = values.length / 6;
            gl.bindBuffer(gl.ARRAY_BUFFER, layer.buffer);
            gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(values), gl.STATIC_DRAW);
        }
        draw();
        return groupRows;
    }

    return {
        setLoad,
        rotate(newYaw, newElevation) {
            yaw = newYaw;
            elevation = newElevation;
            draw();
        },
        redraw: draw,
    };
}

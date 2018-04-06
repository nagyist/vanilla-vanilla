/* tslint:disable */

import {
    EditorChangeHandler,
    EventEmitter,
    SelectionCallback,
    SelectionChangeHandler,
    TextCallback,
    TextChangeHandler
} from "quill/core";

declare module "quill/core" {
    // Type definitions for Quill 1.3
    // Project: https://github.com/quilljs/quill/
    // Definitions by: Sumit <https://github.com/sumitkm>
    //                 Guillaume <https://github.com/guillaume-ro-fr>
    //                 James Garbutt <https://github.com/43081j>
    // Definitions: https://github.com/DefinitelyTyped/DefinitelyTyped

    import { Blot, Parent } from 'parchment/dist/src/blot/abstract/blot';
    import Container from 'parchment/dist/src/blot/abstract/container';

    /**
     * A stricter type definition would be:
     *
     *   type DeltaOperation ({ insert: any } | { delete: number } | { retain: number }) & OptionalAttributes;
     *
     *  But this would break a lot of existing code as it would require manual discrimination of the union types.
     */
    export type DeltaOperation = { insert?: any, delete?: number, retain?: number } & OptionalAttributes;
    export type Sources = "api" | "user" | "silent";

    export interface Key {
        key: string;
        shortKey?: boolean;
    }

    export interface StringMap {
        [key: string]: any;
    }

    export interface OptionalAttributes {
        attributes?: StringMap;
    }

    export interface KeyboardStatic {
        addBinding(key: Key, callback: (range: RangeStatic, context: any) => void): void;
        addBinding(key: Key, context: any, callback: (range: RangeStatic, context: any) => void): void;
    }

    export interface ClipboardStatic {
        addMatcher(selectorOrNodeType: string|number, callback: (node: any, delta: DeltaStatic) => DeltaStatic): void;
        dangerouslyPasteHTML(html: string, source?: Sources): void;
        dangerouslyPasteHTML(index: number, html: string, source?: Sources): void;
    }

    export interface QuillOptionsStatic {
        debug?: string;
        modules?: StringMap;
        placeholder?: string;
        readOnly?: boolean;
        theme?: string;
        formats?: string[];
        bounds?: HTMLElement | string;
        scrollingContainer?: HTMLElement | string;
        strict?: boolean;
    }

    export interface BoundsStatic {
        bottom: number;
        left: number;
        right: number;
        top: number;
        height: number;
        width: number;
    }

    export interface DeltaStatic {
        ops?: DeltaOperation[];
        retain(length: number, attributes?: StringMap): DeltaStatic;
        delete(length: number): DeltaStatic;
        filter(predicate: (op: DeltaOperation) => boolean): DeltaOperation[];
        forEach(predicate: (op: DeltaOperation) => void): void;
        insert(text: any, attributes?: StringMap): DeltaStatic;
        map<T>(predicate: (op: DeltaOperation) => T): T[];
        partition(predicate: (op: DeltaOperation) => boolean): [DeltaOperation[], DeltaOperation[]];
        reduce<T>(predicate: (acc: T, curr: DeltaOperation, idx: number, arr: DeltaOperation[]) => T, initial: T): T;
        chop(): DeltaStatic;
        length(): number;
        slice(start?: number, end?: number): DeltaStatic;
        compose(other: DeltaStatic): DeltaStatic;
        concat(other: DeltaStatic): DeltaStatic;
        diff(other: DeltaStatic, index?: number): DeltaStatic;
        eachLine(predicate: (line: DeltaStatic, attributes: StringMap, idx: number) => any, newline?: string): DeltaStatic;
        transform(index: number, priority?: boolean): number;
        transform(other: DeltaStatic, priority: boolean): DeltaStatic;
        transformPosition(index: number, priority?: boolean): number;
    }

    export class Delta implements DeltaStatic {
        constructor(ops?: DeltaOperation[] | { ops: DeltaOperation[] });
        ops?: DeltaOperation[];
        retain(length: number, attributes?: StringMap): DeltaStatic;
        delete(length: number): DeltaStatic;
        filter(predicate: (op: DeltaOperation) => boolean): DeltaOperation[];
        forEach(predicate: (op: DeltaOperation) => void): void;
        insert(text: any, attributes?: StringMap): DeltaStatic;
        map<T>(predicate: (op: DeltaOperation) => T): T[];
        partition(predicate: (op: DeltaOperation) => boolean): [DeltaOperation[], DeltaOperation[]];
        reduce<T>(predicate: (acc: T, curr: DeltaOperation, idx: number, arr: DeltaOperation[]) => T, initial: T): T;
        chop(): DeltaStatic;
        length(): number;
        slice(start?: number, end?: number): DeltaStatic;
        compose(other: DeltaStatic): DeltaStatic;
        concat(other: DeltaStatic): DeltaStatic;
        diff(other: DeltaStatic, index?: number): DeltaStatic;
        eachLine(predicate: (line: DeltaStatic, attributes: StringMap, idx: number) => any, newline?: string): DeltaStatic;
        transform(index: number): number;
        transform(other: DeltaStatic, priority: boolean): DeltaStatic;
        transformPosition(index: number): number;
    }

    export interface RangeStatic {
        index: number;
        length: number;
    }

    export class RangeStatic implements RangeStatic {
        constructor();
        index: number;
        length: number;
    }

    export type TextChangeHandler = (delta: DeltaStatic, oldContents: DeltaStatic, source: Sources) => any;
    export type SelectionChangeHandler = (range: RangeStatic, oldRange: RangeStatic, source: Sources) => any;
    export type EditorChangeHandler = ((name: "text-change", delta: DeltaStatic, oldContents: DeltaStatic, source: Sources) => any)
        | ((name: "selection-change", range: RangeStatic, oldRange: RangeStatic, source: Sources) => any);
    export type ScrollEventHandler = (source: Sources, context: object) => any;

    type TextCallback = (eventName: "text-change", handler: TextChangeHandler) => EventEmitter;
    type SelectionCallback = (eventName: "selection-change", handler: SelectionChangeHandler) => EventEmitter;
    type ChangeCallback = (eventName: "editor-change", handler: EditorChangeHandler) => EventEmitter;
    type ScrollEventCallback = (eventName: "scroll-optimize" | "scroll-before-update" | "scroll-update", handler: ScrollEventHandler) => EventEmitter;

    export class EventEmitter {
        static events: {
            EDITOR_CHANGE: 'editor-change';
            SCROLL_BEFORE_UPDATE: 'scroll-before-update';
            SCROLL_OPTIMIZE: 'scroll-optimize';
            SCROLL_UPDATE: 'scroll-update';
            SELECTION_CHANGE: 'selection-change';
            TEXT_CHANGE: 'text-change';
        }

        static sources: {
            API: 'api',
            SILENT: 'silent',
            USER: 'user'
        }

        on: TextCallback;
        once: TextCallback;
        off: TextCallback;

        on: SelectionCallback;
        once: SelectionCallback;
        off: SelectionCallback;

        on: ChangeCallback;
        once: ChangeCallback;
        off: ChangeCallback;

        on: ScrollEventCallback;
        once: ScrollEventCallback;
        off: ScrollEventCallback;
    }

    class Quill extends EventEmitter {

        root: HTMLDivElement;
        clipboard: ClipboardStatic;
        scroll: Container;
        container: HTMLDivElement;

        constructor(container: string | Element, options?: QuillOptionsStatic);
        deleteText(index: number, length: number, source?: Sources): DeltaStatic;
        disable(): void;
        enable(enabled?: boolean): void;
        getContents(index?: number, length?: number): DeltaStatic;
        getLength(): number;
        getText(index?: number, length?: number): string;
        insertEmbed(index: number, type: string, value: any, source?: Sources): DeltaStatic;
        insertText(index: number, text: string, source?: Sources): DeltaStatic;
        insertText(index: number, text: string, format: string, value: any, source?: Sources): DeltaStatic;
        insertText(index: number, text: string, formats: StringMap, source?: Sources): DeltaStatic;
        /**
         * @deprecated Remove in 2.0. Use clipboard.dangerouslyPasteHTML(index: number, html: string, source: Sources)
         */
        pasteHTML(index: number, html: string, source?: Sources): string;
        /**
         * @deprecated Remove in 2.0. Use clipboard.dangerouslyPasteHTML(html: string, source: Sources): void;
         */
        pasteHTML(html: string, source?: Sources): string;
        setContents(delta: DeltaStatic, source?: Sources): DeltaStatic;
        setText(text: string, source?: Sources): DeltaStatic;
        update(source?: Sources): void;
        updateContents(delta: DeltaStatic, source?: Sources): DeltaStatic;

        format(name: string, value: any, source?: Sources): DeltaStatic;
        formatLine(index: number, length: number, source?: Sources): DeltaStatic;
        formatLine(index: number, length: number, format: string, value: any, source?: Sources): DeltaStatic;
        formatLine(index: number, length: number, formats: StringMap, source?: Sources): DeltaStatic;
        formatText(index: number, length: number, source?: Sources): DeltaStatic;
        formatText(index: number, length: number, format: string, value: any, source?: Sources): DeltaStatic;
        formatText(index: number, length: number, formats: StringMap, source?: Sources): DeltaStatic;
        getFormat(range?: RangeStatic): StringMap;
        getFormat(index: number, length?: number): StringMap;
        removeFormat(index: number, length: number, source?: Sources): DeltaStatic;

        blur(): void;
        focus(): void;
        getBounds(index: number, length?: number): BoundsStatic;
        getSelection(focus?: boolean): RangeStatic;
        hasFocus(): boolean;
        setSelection(index: number, length: number, source?: Sources): void;
        setSelection(range: RangeStatic, source?: Sources): void;

        // static methods: debug, import, register, find
        static debug(level: string|boolean): void;
        static import(path: string): any;
        static register(path: string, def: any, suppressWarning?: boolean): void;
        static register(defs: StringMap, suppressWarning?: boolean): void;
        static find(domNode: Node, bubble?: boolean): Quill | any;

        addContainer(classNameOrDomNode: string|Node, refNode?: Node): any;
        getModule(name: string): any;

        // Blot interface is not exported on Parchment
        getIndex(blot: any): number;
        getLeaf(index: number): any;
        getLine(index: number): [any, number];
        getLines(index?: number, length?: number): any[];
        getLines(range: RangeStatic): any[];
    }


    export type BlotConstructor<T extends Blot> = {
        blotName: string;
        new(node: Node): T;
    }

    export { Blot, Parent };

    export default Quill;
}

declare module "quill/blots/block" {
    import Block from "parchment/dist/src/blot/block";
    import BlockEmbed from "parchment/dist/src/blot/embed";

    export { BlockEmbed };
    export default Block;
}

declare module "quill/blots/inline" {
    import Inline from "parchment/dist/src/blot/inline";
    export default Inline;
}

declare module "quill/blots/embed" {
    import Embed from "parchment/dist/src/blot/embed";
    export default Embed;
}

declare module "quill/blots/text" {
    import Text from "parchment/dist/src/blot/text";
    export default Text;
}

declare module "quill/core/emitter";
declare module "quill/core/module" {
    import Quill from "quill/core";
    export default class Module {
        constructor(quill: Quill, options: object)
    }
}
declare module "quill/core/theme";
declare module "quill/modules/clipboard";
declare module "quill/modules/formula";
declare module "quill/modules/history";
declare module "quill/modules/keyboard";
declare module "quill/modules/syntax";
declare module "quill/modules/toolbar";

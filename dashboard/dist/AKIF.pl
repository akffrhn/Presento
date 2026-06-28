% ============================================================
%  Simple Prolog Program — Family Relationships
% ============================================================

% --- Facts: parent(Parent, Child) ---
parent(tom, bob).
parent(tom, liz).
parent(bob, ann).
parent(bob, pat).

% --- Facts: male / female ---
male(tom).
male(bob).
male(pat).
female(liz).
female(ann).

% ============================================================
%  Rules
% ============================================================

% father(X, Y) — X is the father of Y
father(X, Y) :- parent(X, Y), male(X).

% mother(X, Y) — X is the mother of Y
mother(X, Y) :- parent(X, Y), female(X).

% grandparent(X, Z) — X is a grandparent of Z
grandparent(X, Z) :- parent(X, Y), parent(Y, Z).

% sibling(X, Y) — X and Y share a parent (and are not the same person)
sibling(X, Y) :- parent(P, X), parent(P, Y), X \= Y.

% ancestor(X, Y) — X is an ancestor of Y (recursive)
ancestor(X, Y) :- parent(X, Y).
ancestor(X, Y) :- parent(X, Z), ancestor(Z, Y).

% ============================================================
%  How to run (using SWI-Prolog)
% ============================================================
%
%  1. Install SWI-Prolog:  https://www.swi-prolog.org/
%  2. Load this file:
%       ?- [family].
%  3. Try these queries:
%
%       ?- father(tom, bob).          % true
%       ?- mother(tom, liz).          % false  (tom is male)
%       ?- grandparent(tom, ann).     % true
%       ?- sibling(ann, pat).         % true
%       ?- ancestor(tom, pat).        % true
%       ?- grandparent(tom, X).       % X = ann ; X = pat
%       ?- sibling(X, Y).             % lists all sibling pairs
% ============================================================

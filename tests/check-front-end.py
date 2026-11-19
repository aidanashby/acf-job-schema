"""Fetch each fixture post and check its JobPosting JSON-LD against the manifest from setup.php.

Usage: python tests/check-front-end.py manifest.json   (exit code 0 = all passed)
"""

import json
import re
import sys
import urllib.request

SCRIPT = re.compile(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', re.S)


def job_postings(html):
    """Every JobPosting on the page, standalone or inside an @graph."""
    found = []
    for block in SCRIPT.findall(html):
        data = json.loads(block)
        items = data.get("@graph", [data]) if isinstance(data, dict) else data
        found += [i for i in items if isinstance(i, dict) and "JobPosting" in (i.get("@type") if isinstance(i.get("@type"), list) else [i.get("@type")])]
    return found


def get(obj, path):
    for part in path.split("."):
        if not isinstance(obj, dict) or part not in obj:
            return KeyError
        obj = obj[part]
    return obj


def main():
    with open(sys.argv[1], encoding="utf-8") as f:
        manifest = json.load(f)
    failures = checks = 0

    def check(ok, label):
        nonlocal failures, checks
        checks += 1
        if not ok:
            failures += 1
            print("FAIL: " + label)

    for case in manifest:
        slug, expect = case["slug"], case["expect"]
        with urllib.request.urlopen(case["url"]) as response:
            html = response.read().decode("utf-8")
        jobs = job_postings(html)

        if not expect["present"]:
            check(not jobs, f"{slug}: no JobPosting (found {len(jobs)})")
            continue
        check(len(jobs) == 1, f"{slug}: exactly one JobPosting (found {len(jobs)})")
        if not jobs:
            continue
        job = jobs[0]
        for path, value in expect.get("equals", {}).items():
            actual = get(job, path)
            check(actual == value, f"{slug}: {path} is {value!r} (got {actual!r})")
        for key in expect.get("absent_keys", []):
            check(key not in job, f"{slug}: no {key}")
        if "equals_post_date" in expect:
            # post_date is site time; the plugin outputs it as an ISO datetime with offset.
            actual = job.get(expect["equals_post_date"], "")
            check(actual.startswith(case["post_date"].replace(" ", "T")), f"{slug}: datePosted from publish date (got {actual!r})")

    print(f"All {checks} front-end checks passed." if not failures else f"{failures} of {checks} front-end checks failed.")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
